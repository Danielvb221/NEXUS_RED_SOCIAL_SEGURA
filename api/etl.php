
<?php

/**
 * ============================================================
 * NEXUS BI - API DE EJECUCIÓN ETL
 * ============================================================
 * PHP / XAMPP -> Cloudflare Tunnel -> FastAPI / Google Colab
 *
 * Etapas:
 *   extract
 *   transform
 *   load
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');


// ============================================================
// 1. RESPUESTA JSON CENTRALIZADA
// ============================================================

function etl_reply(int $status, array $body): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    http_response_code($status);

    $json = json_encode(
        $body,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        echo '{"ok":false,"success":false,"message":"Error al generar respuesta JSON."}';
    } else {
        echo $json;
    }

    exit;
}


// ============================================================
// 2. CAPTURAR ERRORES PHP Y EVITAR HTML
// ============================================================

error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_error_handler(function ($severity, $message, $file, $line) {

    // Respetar errores que hayan sido silenciados.
    if (!(error_reporting() & $severity)) {
        return false;
    }

    error_log(
        "[NEXUS ETL PHP] {$message} en {$file}:{$line}"
    );

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'message' => 'Se produjo un error interno de PHP.',
        'error_type' => $severity,
        'details' => $message,
        'line' => $line
    ]);

    return true;
});


// ============================================================
// 3. CAPTURAR ERRORES FATALES
// ============================================================

register_shutdown_function(function () {

    $error = error_get_last();

    if (!$error) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
        E_USER_ERROR
    ];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    error_log(
        "[NEXUS ETL FATAL] {$error['message']} en {$error['file']}:{$error['line']}"
    );

    // Si ya existe una respuesta parcial, limpiarla.
    if (ob_get_length() !== false) {
        ob_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'success' => false,
        'message' => 'Error fatal interno de PHP.',
        'details' => $error['message'],
        'file' => basename($error['file']),
        'line' => $error['line']
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

});


// ============================================================
// 4. ACTIVAR BUFFER DE SALIDA
// ============================================================

ob_start();


// ============================================================
// 5. VALIDAR MÉTODO HTTP
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    etl_reply(405, [
        'ok' => false,
        'success' => false,
        'message' => 'Método no permitido. Utiliza POST.'
    ]);

}


// ============================================================
// 6. CARGAR CONFIGURACIÓN
// ============================================================

$configPath = __DIR__ . '/../config/etl.php';

if (!file_exists($configPath)) {

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'message' => 'No se encuentra config/etl.php.'
    ]);

}

require_once $configPath;


// ============================================================
// 7. VALIDAR CONFIGURACIÓN
// ============================================================

if (
    !defined('NEXUS_COLAB_ETL_URL') ||
    !defined('NEXUS_COLAB_ETL_TOKEN')
) {

    etl_reply(503, [
        'ok' => false,
        'success' => false,
        'message' => 'La configuración de Colab no está definida.'
    ]);

}

$colabUrl = trim(NEXUS_COLAB_ETL_URL);
$colabToken = trim(NEXUS_COLAB_ETL_TOKEN);

$timeout = defined('NEXUS_ETL_TIMEOUT')
    ? (int) NEXUS_ETL_TIMEOUT
    : 300;

$connectTimeout = defined('NEXUS_ETL_CONNECT_TIMEOUT')
    ? (int) NEXUS_ETL_CONNECT_TIMEOUT
    : 20;


// ============================================================
// 8. VALIDAR URL Y TOKEN
// ============================================================

if (empty($colabUrl) || empty($colabToken)) {

    etl_reply(503, [
        'ok' => false,
        'success' => false,
        'message' => 'La URL o el token de Colab están vacíos.'
    ]);

}

if (!filter_var($colabUrl, FILTER_VALIDATE_URL)) {

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'message' => 'La URL de Colab no tiene un formato válido.'
    ]);

}


// ============================================================
// 9. LEER JSON DEL FRONTEND
// ============================================================

$rawInput = file_get_contents('php://input');

$data = json_decode($rawInput ?: '', true);

if (!is_array($data)) {

    etl_reply(400, [
        'ok' => false,
        'success' => false,
        'message' => 'El cuerpo JSON recibido es inválido.',
        'json_error' => json_last_error_msg()
    ]);

}


// ============================================================
// 10. VALIDAR ETAPA
// ============================================================

$stage = $data['stage'] ?? '';

$stagesAllowed = [
    'extract',
    'transform',
    'load'
];

if (!in_array($stage, $stagesAllowed, true)) {

    etl_reply(400, [
        'ok' => false,
        'success' => false,
        'message' => 'Etapa ETL inválida.',
        'stage_received' => $stage,
        'stages_allowed' => $stagesAllowed
    ]);

}


// ============================================================
// 11. VALIDAR CURL
// ============================================================

if (!function_exists('curl_init')) {

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'message' => 'La extensión cURL de PHP no está habilitada.'
    ]);

}


// ============================================================
// 12. PREPARAR ENDPOINT
// ============================================================

$endpoint = rtrim($colabUrl, '/') . '/' . $stage;


// ============================================================
// 13. PREPARAR PAYLOAD JSON
// ============================================================

$payload = json_encode(
    [
        'stage' => $stage
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($payload === false) {

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'message' => 'No se pudo generar el JSON de la solicitud.'
    ]);

}


// ============================================================
// 14. CREAR CURL
// ============================================================

$ch = curl_init($endpoint);

if ($ch === false) {

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'message' => 'No se pudo inicializar cURL.'
    ]);

}


// ============================================================
// 15. CONFIGURAR CURL
// ============================================================

curl_setopt_array($ch, [

    CURLOPT_POST => true,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $colabToken
    ],

    CURLOPT_POSTFIELDS => $payload,

    CURLOPT_CONNECTTIMEOUT => $connectTimeout,

    CURLOPT_TIMEOUT => $timeout,

    CURLOPT_SSL_VERIFYPEER => true,

    CURLOPT_SSL_VERIFYHOST => 2,

    CURLOPT_FOLLOWLOCATION => false,

    CURLOPT_HEADER => false

]);


// ============================================================
// 16. EJECUTAR SOLICITUD
// ============================================================

$response = curl_exec($ch);

$curlError = curl_error($ch);

$curlErrorNumber = curl_errno($ch);

$httpCode = (int) curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$contentType = curl_getinfo(
    $ch,
    CURLINFO_CONTENT_TYPE
);

curl_close($ch);


// ============================================================
// 17. ERROR DE CONEXIÓN
// ============================================================

if ($response === false) {

    etl_reply(502, [
        'ok' => false,
        'success' => false,
        'stage' => $stage,
        'message' => 'Error de comunicación entre NEXUS y Google Colab.',
        'details' => $curlError,
        'curl_code' => $curlErrorNumber,
        'http_code' => $httpCode
    ]);

}


// ============================================================
// 18. LIMPIAR RESPUESTA
// ============================================================

$response = trim($response);


// ============================================================
// 19. VALIDAR QUE COLAB DEVUELVA JSON
// ============================================================

$colabResponse = json_decode(
    $response,
    true
);

$jsonError = json_last_error();

if (
    $jsonError !== JSON_ERROR_NONE ||
    !is_array($colabResponse)
) {

    etl_reply(502, [
        'ok' => false,
        'success' => false,
        'stage' => $stage,
        'message' => 'Colab devolvió una respuesta que no es JSON válido.',
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'json_error' => json_last_error_msg(),
        'response_preview' => substr($response, 0, 3000)
    ]);

}


// ============================================================
// 20. VALIDAR HTTP
// ============================================================

if ($httpCode < 200 || $httpCode >= 300) {

    $message = $colabResponse['message']
        ?? $colabResponse['detail']
        ?? $colabResponse['error']
        ?? 'Error HTTP recibido desde Colab.';

    etl_reply(
        $httpCode >= 400 && $httpCode <= 599
            ? $httpCode
            : 502,
        [
            'ok' => false,
            'success' => false,
            'stage' => $stage,
            'message' => $message,
            'details' => $colabResponse,
            'http_code' => $httpCode
        ]
    );

}


// ============================================================
// 21. VALIDAR RESPUESTA DE COLAB
// ============================================================

$stageSuccess = $colabResponse['success']
    ?? $colabResponse['ok']
    ?? (
        isset($colabResponse['status'])
        && $colabResponse['status'] === 'ok'
    );

if ($stageSuccess === false) {

    $message = $colabResponse['message']
        ?? $colabResponse['detail']
        ?? $colabResponse['error']
        ?? 'La etapa ETL ha fallado dentro de Colab.';

    etl_reply(500, [
        'ok' => false,
        'success' => false,
        'stage' => $stage,
        'message' => $message,
        'details' => $colabResponse,
        'http_code' => $httpCode
    ]);

}


// ============================================================
// 22. RESPUESTA EXITOSA
// ============================================================

$colabResponse['ok'] = true;

$colabResponse['success'] = true;

$colabResponse['stage'] = $stage;

$colabResponse['http_code'] = $httpCode;


// ============================================================
// 23. DEVOLVER RESULTADO
// ============================================================

etl_reply(200, $colabResponse);

?>
