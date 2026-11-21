# Guía de instalación de NEXUS

Esta guía describe la preparación del entorno local y las bases de datos para ejecutar el proyecto. Los nombres de menú pueden variar según la versión instalada.


## 1. Requisitos previos

- Windows.
- XAMPP con Apache y PHP.
- Microsoft SQL Server/Azure SQL y acceso para crear bases y objetos.
- Extensiones PHP: `PDO`, `PDO_SQLSRV` y `cURL`.
- Google Colab.
- Cuenta de Cloudflare (para el túnel temporal utilizado por el notebook).

### Verificar PHP

Abre CMD y ejecuta:


```bat
php -v
php -m
```

Si `php` no se reconoce, agrega la carpeta PHP de XAMPP al PATH de Windows o ejecuta PHP desde su ruta, por ejemplo `C:\xampp\php\php.exe`.

En la lista de módulos de PHP comprueba que aparezcan PDO y cURL. La conexión a SQL Server requiere instalar los controladores Microsoft SQL Server para la versión de PHP instalada y habilitar `pdo_sqlsrv`/PDO_SQLSRV. Reinicia Apache después de cambiar `php.ini`.


## 2. Colocar el proyecto en XAMPP

1. Descomprime el repositorio.
2. Copia la carpeta del proyecto a `C:\xampp\htdocs\NEXUS`.
3. Inicia Apache desde XAMPP Control Panel.
4. En el navegador, visita `http://localhost/NEXUS/`.

Si el proyecto se instala en otra carpeta, asegúrate de mantener las rutas relativas internas y utiliza la URL de esa carpeta.


## 3. Crear las bases de datos

En Azure Portal o SQL Server Management Studio, crea las siguientes bases:


```sql
CREATE DATABASE NEXUS_OLTP;
GO
CREATE DATABASE NEXUS;
GO
```

### OLTP

1. Selecciona la base `NEXUS_OLTP`.
2. Abre el archivo `base de datos/bd OLTP/NEXUS_OLTP.sql`.
3. Ejecuta el script.

### Data Warehouse

1. Selecciona la base `NEXUS`.
2. Abre `base de datos/bd DW/NEXUS_DW.sql`.
3. Ejecuta el script.

Los scripts crean objetos faltantes, pero no sustituyen una copia de seguridad. Revisa su contenido antes de ejecutarlos en un entorno que ya tenga datos.


## 4. Configurar Azure SQL

Si utilizas Azure SQL:

1. En Azure Portal, abre el servidor lógico SQL.
2. En Networking/Redes o Firewall, agrega la IP pública desde la que se conectará XAMPP.
3. Autoriza solo las IP necesarias; evita reglas abiertas a todo Internet.
4. Comprueba que el usuario SQL tenga permisos sobre las bases `NEXUS_OLTP` y `NEXUS`.
5. Usa el nombre DNS del servidor, normalmente con formato `nombre.database.windows.net`.

La IP que necesita permiso es la del entorno que se conecta. Colab y tu PC pueden tener IP diferentes; si ambos acceden directamente a Azure SQL, sus IP deben estar autorizadas.


## 5. Configurar variables de entorno para PHP/Apache

El proyecto PHP utiliza `getenv()` para obtener las credenciales. El archivo `config/.env.example` sirve como plantilla y **no se carga automáticamente**.

Configura estas variables en el entorno de Windows o en la configuración de Apache de tu instalación local:


```text
NEXUS_SQL_SERVER=INGRESAR_SERVIDOR_AZURE_SQL
NEXUS_SQL_USER=INGRESAR_USUARIO_SQL
NEXUS_SQL_PASSWORD=INGRESAR_CONTRASENA_SQL
NEXUS_COLAB_ETL_URL=INGRESAR_URL_DE_COLAB
NEXUS_COLAB_ETL_TOKEN=INGRESAR_TOKEN_DE_COLAB
```

Reemplaza los marcadores por los valores locales. No guardes las credenciales reales en el repositorio.

**Importante:** Apache debe recibir esas variables en su proceso. Si las agregas a Variables de entorno de Windows, cierra y vuelve a iniciar XAMPP/Apache. Si Apache no las hereda, configura las variables para el servicio/proceso Apache siguiendo la documentación de tu instalación y reinicia Apache.

La configuración PHP se encuentra en `config/database.php` y `config/etl.php`. Si estás haciendo una instalación local solo para pruebas y prefieres editar esos archivos, usa valores locales y no subas los cambios con credenciales a GitHub.


## 6. Preparar Google Colab y ETL

1. Abre `colab/NEXUS.ipynb`.
2. Súbelo o impórtalo a Google Colab.
3. Conecta el runtime.
4. Ejecuta las celdas **en orden**, desde el inicio.
5. Cuando lo solicite, ingresa la contraseña de Azure SQL.
6. Cuando lo solicite, ingresa un token de integración. Genera uno nuevo, largo y aleatorio; no reutilices el token que se haya publicado o compartido.
7. Revisa la salida de conexión a `NEXUS_OLTP` y `NEXUS`.
8. Autoriza la IP pública actual de Colab en el firewall de Azure SQL si Colab necesita acceso directo.

El notebook instala las dependencias indicadas en sus celdas, incluido el driver `mssql-python`, Pandas, NumPy, FastAPI, Uvicorn, `nest_asyncio`, scikit-learn y Requests. No instales versiones distintas sin verificar compatibilidad.


## 7. Iniciar FastAPI

Ejecuta la celda del notebook que inicia FastAPI. Comprueba que el servicio responda desde la ruta `/health` y que indique que está disponible.

Mantén el runtime de Colab activo mientras el sistema web necesite utilizar la API. Si el runtime se desconecta, la API dejará de responder.


## 8. Crear el túnel Cloudflare

1. Ejecuta la celda de Cloudflare Tunnel del notebook.
2. Espera a que aparezca una URL pública con dominio `trycloudflare.com`.
3. Copia la URL completa que apunta al servicio FastAPI.
4. Configura esa URL en `NEXUS_COLAB_ETL_URL` para PHP.
5. Asegúrate de que el token configurado en PHP sea exactamente el mismo que ingresaste en Colab.

El túnel Quick Tunnel es temporal. Puede cambiar al reiniciar Colab; si cambia, actualiza la variable URL en PHP y reinicia Apache.


## 9. Abrir el sistema

Con Apache activo y la API de Colab accesible, abre:


```text
http://localhost/NEXUS/
```

Revisa los paneles de estado de conexiones. Si OLTP, DW o ETL no están disponibles, consulta la guía `CONEXIONES.md`.


## 10. Apagar y volver a iniciar

Para una sesión nueva:

1. Inicia Apache desde XAMPP.
2. Abre el notebook de Colab.
3. Conecta el runtime y ejecuta las celdas en orden.
4. Ingresa contraseña y token cuando se soliciten.
5. Inicia FastAPI y el túnel.
6. Si la URL pública cambió, actualiza PHP.
7. Abre NEXUS en el navegador.

