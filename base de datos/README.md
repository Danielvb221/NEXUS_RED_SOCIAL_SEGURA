# Scripts de bases de datos

- `bd OLTP/NEXUS_OLTP.sql`: esquema transaccional; ejecutarlo en la base `NEXUS_OLTP`.
- `bd DW/NEXUS_DW.sql`: esquema analítico; ejecutarlo en la base `NEXUS`.

Los scripts usan `USE` para seleccionar bases ya creadas y contienen verificaciones de existencia para objetos. No asumas que son un backup ni que sincronizan el esquema con una base existente. Lee el script y compara con tus bases actuales antes de ejecutar; realiza respaldo si hay datos importantes.
