<?php
/** Azure SQL connections for NEXUS. Keep credentials out of public repositories. */
// CONFIGURACIÓN: ingrese los datos de conexión de Azure SQL en las variables de entorno.
$server = getenv('NEXUS_SQL_SERVER') ?: 'INGRESAR_SERVIDOR_AZURE_SQL';
$username = getenv('NEXUS_SQL_USER') ?: 'INGRESAR_USUARIO_SQL';
$password = getenv('NEXUS_SQL_PASSWORD') ?: 'INGRESAR_CONTRASENA_SQL';

if ($password === '' || $password === 'INGRESAR_CONTRASENA_SQL' || $server === 'INGRESAR_SERVIDOR_AZURE_SQL' || $username === 'INGRESAR_USUARIO_SQL') {
    error_log('NEXUS: Ingrese servidor, usuario y contraseña de Azure SQL en la configuración del entorno.');
    $pdo_oltp = null;
    $pdo_dw = null;
    $conexion_oltp_ok = false;
    $conexion_dw_ok = false;
    $db_error = 'Ingresar servidor, usuario y contraseña de Azure SQL.';
    return;
}

function nexusConnectSqlServer(string $server, string $database, string $username, string $password): ?PDO {
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
