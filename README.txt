NEXUS - EDICION AZURE SQL (PHP/XAMPP)
======================================
Prototipo web NEXUS integrado con Azure SQL. Consulta README.md para la presentación del proyecto y docs/ para las guías completas.

Archivos principales:
- index.php: interfaz y operaciones
- assets/: estilos, JavaScript, imágenes y logo
- config/database.php: conexiones PDO_SQLSRV a NEXUS_OLTP y NEXUS
- config/etl.php: configuración del proxy hacia Colab
- api/etl.php: endpoints proxy ETL
- colab/NEXUS.ipynb: notebook ETL/API
- base de datos/: scripts OLTP y Data Warehouse

IMPORTANTE: no se incluyen credenciales reales. Configura config/secrets.local.php a partir de config/secrets.example.php. El archivo local está excluido de Git.

Estado del prototipo: algunas capas ETL, Data Warehouse y Predicciones de la interfaz son demostrativas/pendientes de integración real. Confirma los esquemas SQL con tus bases reales antes de ejecutar.
