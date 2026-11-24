# NEXUS — Red Social Segura

NEXUS es un prototipo web para gestión y análisis de usuarios que integra PHP/XAMPP, Azure SQL Server y un notebook de Google Colab para procesamiento ETL, analítica y API FastAPI.

> **Estado del prototipo:** el README original indica que las capas ETL, Data Warehouse y Predicciones de la interfaz son demostrativas/pendientes de integración real en ciertos componentes. Verifica el esquema OLTP y DW con tus bases antes de ejecutar operaciones.

## Tecnologías
- PHP y PDO_SQLSRV
- HTML, CSS y JavaScript
- SQL Server / Azure SQL
- Python en Google Colab
- FastAPI, Uvicorn y Cloudflare Tunnel (túnel temporal)

## Estructura
```text
NEXUS/
├── api/                 # Proxy PHP hacia la API ETL de Colab
├── assets/              # CSS, JavaScript, imágenes y logo
├── base de datos/       # Scripts SQL OLTP y Data Warehouse
├── colab/               # Notebook de ETL y API
├── config/               # Configuración y ejemplos (sin secretos reales)
├── docs/                 # Guías de instalación, conexiones y uso
├── index.php             # Aplicación web
└── test_connection.php   # Diagnóstico de conexiones SQL
```

## Inicio rápido
1. Revisa `docs/INSTALACION.md`.
2. Configura las credenciales locales copiando `config/secrets.example.php` a `config/secrets.local.php` y completando los valores. **No subas el archivo local.**
3. Crea/configura Azure SQL y ejecuta los scripts en `base de datos/` en el orden indicado en la guía.
4. Configura XAMPP y abre `http://localhost/NEXUS/test_connection.php`.
5. Abre `http://localhost/NEXUS/index.php`.
6. Para las funciones ETL, ejecuta el notebook `colab/NEXUS.ipynb` siguiendo `docs/ETL_COLAB.md` y configura la URL/token de PHP.

## Seguridad
No publiques contraseñas, tokens, URLs de túneles activos, archivos `secrets.local.php`, `.env` ni datos personales. Si un secreto se publicó anteriormente, revócalo o cámbialo.

## Documentación
- [Instalación](docs/INSTALACION.md)
- [Conexiones](docs/CONEXIONES.md)
- [Uso](docs/USO.md)
- [ETL y Colab](docs/ETL_COLAB.md)
- [Scripts SQL](base%20de%20datos/README.md)

## Licencia
No se ha asignado una licencia de distribución. Todos los derechos quedan reservados al autor, salvo que se indique lo contrario.
