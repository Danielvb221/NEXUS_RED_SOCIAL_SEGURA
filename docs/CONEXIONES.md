# Conexiones NEXUS

## PHP a Azure SQL
`config/database.php` conecta por PDO_SQLSRV a `NEXUS_OLTP` y `NEXUS`. Los valores se obtienen de variables de entorno o de `config/secrets.local.php`. No se imprimen secretos en la interfaz.

## Valores de configuración
| Clave | Valor a completar |
|---|---|
| `sql_server` | Host del servidor Azure SQL, por ejemplo `servidor.database.windows.net` |
| `sql_user` | Usuario de SQL |
| `sql_password` | Contraseña de SQL |
| `colab_etl_url` | URL HTTPS actual del túnel Colab |
| `colab_etl_token` | Token compartido PHP–Colab |

## Firewall Azure
Autoriza únicamente IPs que necesitan conectarse (tu PC y la IP actual de Colab si aplica). Colab puede cambiar su IP al reiniciar. No habilites reglas de acceso abiertas a todo Internet.

## Problemas frecuentes
- `could not find driver`: habilita/instala PDO_SQLSRV compatible con PHP.
- `Client IP not allowed`: agrega la IP pública actual al firewall Azure SQL.
- Timeout: revisa firewall, servidor, puerto 1433 y disponibilidad.
- Certificado TLS: conserva `Encrypt=yes` y `TrustServerCertificate=no`; no desactives la validación como solución permanente.
- Error de esquema: compara las tablas y columnas reales con las consultas de `index.php` y el notebook.
