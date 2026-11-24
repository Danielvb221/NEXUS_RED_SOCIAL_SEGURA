<?php
/** NEXUS Azure SQL connection configuration. Keep real secrets in secrets.local.php or environment variables. */
$localSecrets = [];
$localSecretsPath = __DIR__ . '/secrets.local.php';
if (is_file($localSecretsPath)) {
    $loaded = require $localSecretsPath;
    if (is_array($loaded)) $localSecrets = $loaded;
}
$server = getenv('NEXUS_SQL_SERVER') ?: ($localSecrets['sql_server'] ?? 'INGRESAR_SERVIDOR_AZURE_SQL');
$username = getenv('NEXUS_SQL_USER') ?: ($localSecrets['sql_user'] ?? 'INGRESAR_USUARIO_SQL');
$password = getenv('NEXUS_SQL_PASSWORD') ?: ($localSecrets['sql_password'] ?? 'INGRESAR_CONTRASENA_SQL');

function nexusConnectSqlServer(string $server, string $database, string $username, string $password): ?PDO {
    if (str_starts_with($server, 'INGRESAR_') || str_starts_with($username, 'INGRESAR_') || str_starts_with($password, 'INGRESAR_')) {
        $GLOBALS['nexus_connection_errors'][$database] = 'Configuración pendiente: completa config/secrets.local.php o las variables de entorno.';
        return null;
    }
    try {
        $dsn = "sqlsrv:Server=$server,1433;Database=$database;Encrypt=yes;TrustServerCertificate=no";
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log("NEXUS Azure SQL connection ($database): " . $e->getMessage());
        $GLOBALS['nexus_connection_errors'][$database] = $e->getMessage();
        return null;
    }
}
$pdo_oltp = nexusConnectSqlServer($server, 'NEXUS_OLTP', $username, $password);
$pdo_dw = nexusConnectSqlServer($server, 'NEXUS', $username, $password);
$conexion_oltp_ok = $pdo_oltp !== null;
$conexion_dw_ok = $pdo_dw !== null;
$db_error = $GLOBALS['nexus_connection_errors']['NEXUS_OLTP'] ?? null;
