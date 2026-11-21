<?php
// ============================================================
// 1. CONFIGURACIÓN DEL SERVIDOR COLAB
// ============================================================

if (!defined('NEXUS_COLAB_ETL_URL')) {
    define(
        'NEXUS_COLAB_ETL_URL',
        getenv('NEXUS_COLAB_ETL_URL') ?: 'INGRESAR_URL_DE_COLAB'
    );
}

if (!defined('NEXUS_COLAB_ETL_TOKEN')) {
    define(
        'NEXUS_COLAB_ETL_TOKEN',
        getenv('NEXUS_COLAB_ETL_TOKEN') ?: 'INGRESAR_TOKEN_DE_COLAB'
    );
}

if (!defined('NEXUS_ETL_TIMEOUT')) {
    define('NEXUS_ETL_TIMEOUT', 300);
}

if (!defined('NEXUS_ETL_CONNECT_TIMEOUT')) {
    define('NEXUS_ETL_CONNECT_TIMEOUT', 20);
}


// ============================================================
// 2. VALIDACIÓN DE CONFIGURACIÓN
// ============================================================

function nexusColabConfigured(): bool
{
    return (
        defined('NEXUS_COLAB_ETL_URL') &&
        defined('NEXUS_COLAB_ETL_TOKEN') &&
        !empty(NEXUS_COLAB_ETL_URL) &&
        !empty(NEXUS_COLAB_ETL_TOKEN) &&
        NEXUS_COLAB_ETL_URL !== 'INGRESAR_URL_DE_COLAB' &&
        NEXUS_COLAB_ETL_TOKEN !== 'INGRESAR_TOKEN_DE_COLAB'
    );
}


// ============================================================
// 3. FUNCIÓN GENERAL DE COMUNICACIÓN CON COLAB
// ============================================================

function nexusColabRequest(
    string $endpoint,
    array $payload = []
): array {

    if (!nexusColabConfigured()) {
        return [
            'success' => false,
            'message' => 'Ingrese la URL de Colab y el token de autenticación en la configuración.'
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'message' => 'La extensión cURL de PHP no está habilitada.'
        ];
    }

    $url = rtrim(NEXUS_COLAB_ETL_URL, '/')
        . '/'
        . ltrim($endpoint, '/');

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($jsonPayload === false) {
        return [
            'success' => false,
            'message' => 'No se pudo convertir la solicitud a JSON.',
            'details' => json_last_error_msg()
        ];
    }

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'success' => false,
            'message' => 'No se pudo inicializar cURL.'
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . NEXUS_COLAB_ETL_TOKEN
        ],

        CURLOPT_POSTFIELDS => $jsonPayload,

        CURLOPT_TIMEOUT => NEXUS_ETL_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => NEXUS_ETL_CONNECT_TIMEOUT,

        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_FOLLOWLOCATION => false
    ]);

    $response = curl_exec($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);
    $curlErrorNumber = curl_errno($ch);

    curl_close($ch);

    // --------------------------------------------------------
    // ERROR DE COMUNICACIÓN
    // --------------------------------------------------------

    if ($response === false) {
        return [
            'success' => false,
            'message' => 'Error de comunicación con Colab.',
            'details' => $curlError,
            'curl_code' => $curlErrorNumber,
            'http_code' => $httpCode
        ];
    }

    // --------------------------------------------------------
    // CONVERTIR RESPUESTA JSON
    // --------------------------------------------------------

    $data = json_decode($response, true);

    if (!is_array($data)) {
        return [
            'success' => false,
            'message' => 'Colab devolvió una respuesta inválida.',
            'response' => $response,
            'http_code' => $httpCode
        ];
    }

    // --------------------------------------------------------
    // VALIDAR RESPUESTA HTTP
    // --------------------------------------------------------

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'message' => 'Colab respondió con un error HTTP.',
            'http_code' => $httpCode,
            'data' => $data
        ];
    }

    // --------------------------------------------------------
    // RESPUESTA CORRECTA
    // --------------------------------------------------------

    $data['http_code'] = $httpCode;

    return $data;
}


// ============================================================
// 4. VERIFICAR DISPONIBILIDAD DE COLAB
// ============================================================

function nexusColabHealth(): array
{
    if (!nexusColabConfigured()) {
        return [
            'success' => false,
            'message' => 'Ingrese la URL de Colab y el token de autenticación en la configuración.'
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'message' => 'La extensión cURL de PHP no está habilitada.'
        ];
    }

    $url = rtrim(
        NEXUS_COLAB_ETL_URL,
        '/'
    ) . '/health';

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'success' => false,
            'message' => 'No se pudo inicializar cURL.'
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ],

        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,

        CURLOPT_FOLLOWLOCATION => false
    ]);

    $response = curl_exec($ch);

    $httpCode = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $error = curl_error($ch);
    $errorNumber = curl_errno($ch);

    curl_close($ch);

    // --------------------------------------------------------
    // ERROR DE CONEXIÓN
    // --------------------------------------------------------

    if ($response === false) {
        return [
            'success' => false,
            'message' => 'Colab no responde.',
            'details' => $error,
            'curl_code' => $errorNumber,
            'http_code' => $httpCode
        ];
    }

    // --------------------------------------------------------
    // VALIDAR RESPUESTA JSON
    // --------------------------------------------------------

    $data = json_decode(
        $response,
        true
    );

    if (!is_array($data)) {
        return [
            'success' => false,
            'message' => 'Colab devolvió una respuesta no válida.',
            'response' => $response,
            'http_code' => $httpCode
        ];
    }

    // --------------------------------------------------------
    // VALIDAR ESTADO HTTP
    // --------------------------------------------------------

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'message' => 'El servidor Colab respondió con HTTP ' . $httpCode,
            'http_code' => $httpCode,
            'data' => $data
        ];
    }

    // --------------------------------------------------------
    // SERVIDOR DISPONIBLE
    // --------------------------------------------------------

    return [
        'success' => true,
        'message' => 'Servidor NEXUS BI disponible.',
        'http_code' => $httpCode,
        'data' => $data
    ];
}


// ============================================================
// 5. INFORMACIÓN DE CONFIGURACIÓN DEL ETL
// ============================================================

function nexusColabConfigInfo(): array
{
    return [
        'configured' => nexusColabConfigured(),
        'url' => NEXUS_COLAB_ETL_URL,
        'timeout' => NEXUS_ETL_TIMEOUT,
        'connect_timeout' => NEXUS_ETL_CONNECT_TIMEOUT,
        'token_configured' => !empty(NEXUS_COLAB_ETL_TOKEN)
    ];
}

?>
