# Instalación local (XAMPP + Azure SQL)

## Requisitos
- Windows y XAMPP con una versión de PHP compatible con Microsoft Drivers SQLSRV/PDO_SQLSRV.
- Microsoft ODBC Driver for SQL Server.
- Extensión PHP `pdo_sqlsrv` habilitada.
- Azure SQL con bases `NEXUS_OLTP` y `NEXUS`.
- Acceso de red autorizado por el firewall de Azure SQL.

## 1. Copiar el proyecto
Descomprime el repositorio dentro de `C:\xampp\htdocs\NEXUS`. Comprueba que `index.php` quede en esa carpeta.

## 2. Habilitar drivers PHP
Instala el driver SQL Server compatible con tu versión y arquitectura de PHP. En el `php.ini` utilizado por Apache habilita `pdo_sqlsrv` (y `sqlsrv` si se requiere). Reinicia Apache. Comprueba en `phpinfo()` o `php -m` que PDO_SQLSRV esté disponible.

## 3. Configurar credenciales localmente
Copia `config/secrets.example.php` como `config/secrets.local.php`. Completa servidor, usuario, contraseña, URL ETL y token. No subas `secrets.local.php` a GitHub. También se admiten variables de entorno `NEXUS_SQL_SERVER`, `NEXUS_SQL_USER`, `NEXUS_SQL_PASSWORD`, `NEXUS_COLAB_ETL_URL` y `NEXUS_COLAB_ETL_TOKEN`.

## 4. Preparar Azure SQL
En Azure crea/valida las bases `NEXUS_OLTP` y `NEXUS`. Autoriza la IP pública de tu equipo desde el firewall del servidor SQL (solo la IP necesaria). Ejecuta los scripts SQL correspondientes en cada base, según `base de datos/README.md`. Revisa primero el esquema y evita ejecutar scripts sobre datos de producción sin respaldo.

## 5. Probar conexión
Abre `http://localhost/NEXUS/test_connection.php`. La página muestra si PHP conecta con ambas bases, sin revelar contraseñas. Si falla, consulta `docs/CONEXIONES.md`.

## 6. Abrir aplicación
Abre `http://localhost/NEXUS/index.php`.

## 7. Configurar ETL (opcional)
Sigue `docs/ETL_COLAB.md`. Colab debe permanecer ejecutándose mientras se usa la API y el túnel temporal.
