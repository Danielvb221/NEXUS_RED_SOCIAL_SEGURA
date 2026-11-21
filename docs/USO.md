# Guía básica de uso

## 1. Comprobar el entorno

Antes de usar las funciones de NEXUS, verifica que:

- Apache esté activo.
- Las bases OLTP y DW estén accesibles.
- Colab tenga el runtime conectado.
- FastAPI esté activo.
- El túnel Cloudflare tenga una URL válida.
- PHP tenga configurados servidor, usuario, contraseña, URL ETL y token.

## 2. Gestión de usuarios

Utiliza las opciones de la interfaz NEXUS para consultar y administrar los registros disponibles. Para pruebas, usa datos ficticios y evita cargar información personal real.

## 3. ETL y analítica

La API de Colab permite ejecutar las etapas ETL y consultar salidas analíticas. El flujo general es extracción, transformación, carga y actualización de resultados analíticos. Revisa el estado de cada etapa en el panel y en el notebook.

## 4. Generación de datos ficticios

El notebook incluye un endpoint de generación de usuarios ficticios. Utiliza cantidades moderadas durante las pruebas y confirma que estás conectado a la base de datos de pruebas antes de generar registros.

## 5. Power BI

El Data Warehouse de NEXUS está destinado a la consulta y visualización analítica. Configura Power BI con el origen SQL y las credenciales autorizadas que correspondan. No publiques archivos con credenciales almacenadas ni datos personales.
