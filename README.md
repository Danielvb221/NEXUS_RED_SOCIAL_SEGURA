# NEXUS — Red Social Segura

NEXUS es un proyecto web de gestión y analítica de usuarios que integra PHP, Azure SQL/SQL Server, Python en Google Colab, FastAPI y un flujo ETL hacia un Data Warehouse.

> **Aviso de seguridad:** este repositorio no incluye las contraseñas ni el token de integración. No publiques credenciales, tokens, archivos `.env` reales ni datos personales.

## Componentes

- **Front-End:** interfaz web, estilos CSS y JavaScript (`assets/`).
- **Back-End PHP:** página principal, conexión SQL y endpoint de integración ETL (`index.php`, `config/`, `api/`).
- **Base transaccional (OLTP):** `NEXUS_OLTP`.
- **Data Warehouse:** base `NEXUS`.
- **ETL / analítica:** notebook de Google Colab en `colab/NEXUS.ipynb`.
- **API:** FastAPI ejecutada desde Colab y publicada temporalmente mediante Cloudflare Tunnel.

## Estructura

```text
NEXUS/
├── api/                    # Endpoint PHP de integración ETL
├── assets/                  # CSS, JavaScript e imagen del proyecto
├── base de datos/
│   ├── bd OLTP/            # Script NEXUS_OLTP.sql
│   └── bd DW/              # Script NEXUS_DW.sql
├── colab/                   # Notebook ETL, API y analítica
├── config/                   # Configuración PHP y plantilla de variables
├── docs/                     # Guías de instalación y conexión
├── index.php                 # Aplicación web
├── README.md
└── .gitignore
```

## Requisitos

- Windows con XAMPP (Apache y PHP).
- SQL Server o Azure SQL con permisos para crear/administrar los objetos requeridos.
- PHP con PDO y la extensión PDO_SQLSRV habilitada; también se requiere cURL para la comunicación ETL.
- Google Colab para ejecutar el notebook ETL/API.
- Cloudflare Tunnel (túnel temporal `trycloudflare.com`) para exponer la API de Colab.

Los pasos de instalación están en [`docs/INSTALACION.md`](docs/INSTALACION.md). La configuración de conexiones se detalla en [`docs/CONEXIONES.md`](docs/CONEXIONES.md).

## Bases de datos

1. Crear la base `NEXUS_OLTP` y ejecutar el script `base de datos/bd OLTP/NEXUS_OLTP.sql` dentro de esa base.
2. Crear la base `NEXUS` y ejecutar `base de datos/bd DW/NEXUS_DW.sql` dentro de esa base.
3. Configurar el firewall de Azure SQL para permitir la IP de conexión que corresponda. No habilites acceso indiscriminado.

Los scripts están diseñados para verificar y crear objetos que no existan; revisa cada script antes de ejecutarlo en una base con datos existentes.

## Configuración segura

`config/.env.example` es una **plantilla de referencia**. El PHP del proyecto usa `getenv()`; no carga automáticamente un archivo `.env`. Configura las variables en el entorno de Apache/PHP siguiendo `docs/CONEXIONES.md`.

Variables esperadas:

- `NEXUS_SQL_SERVER`
- `NEXUS_SQL_USER`
- `NEXUS_SQL_PASSWORD`
- `NEXUS_COLAB_ETL_URL`
- `NEXUS_COLAB_ETL_TOKEN`

En Google Colab, el notebook solicita la contraseña de Azure SQL y el token de integración de forma interactiva. Usa el mismo token en Colab y PHP.

## Inicio rápido

1. Instala XAMPP y configura los requisitos de PHP.
2. Copia el proyecto en la carpeta `htdocs`.
3. Crea las bases de datos y ejecuta los scripts SQL.
4. Configura las variables de entorno para Apache.
5. Abre y ejecuta `colab/NEXUS.ipynb` en Google Colab en orden.
6. Inicia FastAPI y Cloudflare Tunnel desde las celdas indicadas en el notebook.
7. Configura la URL pública del túnel en PHP y abre NEXUS desde el navegador.

Consulta la guía completa antes de iniciar: [`docs/INSTALACION.md`](docs/INSTALACION.md).

## Uso responsable de datos

Utiliza datos ficticios o autorizados. No cargues información personal real en repositorios públicos ni en notebooks compartidos. Las funciones de generación de datos del proyecto están destinadas a pruebas.

