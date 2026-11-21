# Guía de conexiones de NEXUS

## Diagrama general

```text
Navegador
    |
    v
XAMPP / Apache / PHP
    |---------------------> Azure SQL: NEXUS_OLTP
    |---------------------> Azure SQL: NEXUS (DW)
    |
    | HTTP POST + Bearer Token
    v
Cloudflare Quick Tunnel (URL temporal)
    |
    v
Google Colab / FastAPI
    |
    |---------------------> Azure SQL: NEXUS_OLTP (extracción y generación de datos)
    |---------------------> Azure SQL: NEXUS (carga DW, analítica, predicciones)
```


## A. PHP a Azure SQL

El archivo `config/database.php` realiza dos conexiones PDO SQL Server:

- `NEXUS_OLTP`: base transaccional.
- `NEXUS`: base del Data Warehouse.

Variables:

| Variable | Valor que debes configurar |
|---|---|
| `NEXUS_SQL_SERVER` | Host del servidor SQL, sin inventar ni incluir una base en el host |
| `NEXUS_SQL_USER` | Usuario SQL autorizado |
| `NEXUS_SQL_PASSWORD` | Contraseña del usuario SQL |

La conexión utiliza cifrado y puerto 1433. El controlador PDO_SQLSRV debe estar instalado y habilitado en PHP.

## B. Colab a Azure SQL

En el notebook, la configuración de servidor y usuario está definida en las celdas de configuración y la contraseña se solicita mediante `getpass`. El notebook conecta con:

- `NEXUS_OLTP`
- `NEXUS`

Colab puede tener una IP pública distinta a la de tu PC. Si Azure SQL tiene firewall, agrega la IP de Colab que el notebook muestra, y vuelve a actualizarla cuando cambie el runtime.

## C. PHP a FastAPI en Colab

PHP usa estas variables:

| Variable | Descripción |
|---|---|
| `NEXUS_COLAB_ETL_URL` | URL base pública del túnel Cloudflare hacia FastAPI |
| `NEXUS_COLAB_ETL_TOKEN` | Token Bearer compartido con el notebook |

El token se envía en el encabezado HTTP `Authorization: Bearer ...`. Debe ser el mismo en ambos lados, suficientemente largo y privado.

## D. Flujo de conexión paso a paso

1. Ejecuta Colab y autentícate en las conexiones SQL.
2. Ingresa el token cuando el notebook lo solicite.
3. Ejecuta la celda que inicia FastAPI.
4. Ejecuta Cloudflare Tunnel y copia la URL `trycloudflare.com`.
5. Configura la URL y el token en las variables del entorno de Apache/PHP.
6. Reinicia Apache si modificaste el entorno.
7. En NEXUS, verifica el estado de las conexiones y prueba la función ETL.

## E. Endpoints usados por el sistema

El notebook documenta endpoints de FastAPI, entre ellos:

- `GET /health`: estado del servicio.
- `POST /run`: pipeline ETL completo.
- `POST /extract`: extracción.
- `POST /transform`: transformación.
- `POST /load`: carga y salida analítica.
- `GET /predictions`: resumen de predicciones.
- `POST /generate-users`: generación de usuarios ficticios.

Las rutas y sus payloads deben verificarse en el notebook antes de integrarlas con otra aplicación o cliente.


## F. Problemas frecuentes

### Azure SQL rechaza la conexión por IP

Agrega la IP pública del equipo o runtime que se conecta en la configuración del firewall del servidor Azure SQL. No confundas la IP de tu PC con la IP de Colab.

### PHP no reconoce PDO_SQLSRV

Instala el controlador Microsoft para la versión exacta de PHP y habilita la extensión correspondiente en `php.ini`. Reinicia Apache y confirma el módulo con `php -m`.

### PHP no reconoce cURL

Habilita la extensión cURL en `php.ini`, reinicia Apache y verifica `php -m`.

### Colab responde, pero PHP no conecta

Verifica la URL del túnel, el token, que FastAPI siga activo, el endpoint y que Apache haya recibido las variables de entorno.

### La URL de Cloudflare cambió

El Quick Tunnel es temporal. Copia la nueva URL y actualiza `NEXUS_COLAB_ETL_URL`; reinicia Apache si corresponde.

### El ETL demora o no responde

Colab puede suspenderse o desconectarse. Mantén el runtime activo y revisa los mensajes del notebook y los logs de PHP/Apache.

