# ETL y API en Google Colab

## Flujo de preparación
1. Abre `colab/NEXUS.ipynb` en Google Colab.
2. Ejecuta las celdas en orden.
3. Cuando se solicite, ingresa la contraseña de Azure SQL y un token compartido. El notebook utiliza entrada interactiva; no escribas secretos en celdas ni guardes outputs con credenciales.
4. Autoriza en Azure SQL la IP pública actual de Colab que muestra el notebook. Retira esa regla cuando ya no la necesites.
5. Inicia FastAPI y luego Cloudflare Tunnel desde las celdas indicadas. La URL `trycloudflare.com` es temporal y cambia al reiniciar.
6. Copia la URL HTTPS temporal y configura `colab_etl_url` en `config/secrets.local.php`; coloca el mismo token que ingresaste en Colab en `colab_etl_token`.
7. Mantén Colab y el túnel activos mientras la web necesite acceder al servicio.

## Endpoints documentados por el notebook
- `POST /generate-users`
- `POST /extract`
- `POST /transform`
- `POST /load`
- `POST /run`
- `GET /predictions`
- `GET /health`

La interfaz y el notebook pueden tener dependencias de esquema. Confirma los objetos SQL y realiza pruebas con pocos registros antes de generar datos en volumen.
