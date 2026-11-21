/* NEXUS_OLTP - esquema transaccional SQL Server/Azure SQL
   Basado en database.sql del proyecto NEXUS_XAMPP (1).zip.
   Ejecutar en la base NEXUS_OLTP. No elimina datos ni tablas existentes.
*/
USE [NEXUS_OLTP];
GO

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID('dbo.usuarios','U') IS NULL
BEGIN
    CREATE TABLE dbo.usuarios (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_usuarios PRIMARY KEY,
        nombre NVARCHAR(80) NOT NULL,
        apellidos NVARCHAR(100) NOT NULL,
        dni NVARCHAR(8) NOT NULL,
        genero NVARCHAR(30) NULL,
        fecha_nacimiento DATE NULL,
        correo NVARCHAR(150) NULL,
        telefono NVARCHAR(20) NULL,
        pais NVARCHAR(60) NOT NULL CONSTRAINT DF_usuarios_pais DEFAULT N'Perú',
        departamento NVARCHAR(60) NULL,
        provincia NVARCHAR(60) NULL,
        distrito NVARCHAR(60) NULL,
        domicilio NVARCHAR(180) NULL,
        estado NVARCHAR(30) NOT NULL CONSTRAINT DF_usuarios_estado DEFAULT N'Activa',
        riesgo TINYINT NOT NULL CONSTRAINT DF_usuarios_riesgo DEFAULT 1,
        fecha_registro DATE NOT NULL CONSTRAINT DF_usuarios_fecha_registro DEFAULT CONVERT(date,GETDATE()),
        CONSTRAINT UQ_usuarios_dni UNIQUE (dni),
        CONSTRAINT CK_usuarios_riesgo CHECK (riesgo BETWEEN 1 AND 5)
    );
END;
GO
IF OBJECT_ID('dbo.interacciones','U') IS NULL
BEGIN
    CREATE TABLE dbo.interacciones (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_interacciones PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo NVARCHAR(30) NULL,
        categoria NVARCHAR(60) NULL,
        formato NVARCHAR(20) NULL,
        fecha DATE NULL,
        dispositivo NVARCHAR(30) NULL,
        sistema_operativo NVARCHAR(30) NULL,
        tiempo_segundos INT NOT NULL CONSTRAINT DF_interacciones_tiempo DEFAULT 0,
        vistas INT NOT NULL CONSTRAINT DF_interacciones_vistas DEFAULT 1,
        CONSTRAINT FK_interacciones_usuarios FOREIGN KEY(usuario_id)
            REFERENCES dbo.usuarios(id) ON DELETE CASCADE,
        CONSTRAINT CK_interacciones_tiempo CHECK (tiempo_segundos >= 0),
        CONSTRAINT CK_interacciones_vistas CHECK (vistas >= 0)
    );
END;
GO
IF OBJECT_ID('dbo.sesiones','U') IS NULL
BEGIN
    CREATE TABLE dbo.sesiones (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_sesiones PRIMARY KEY,
        usuario_id INT NOT NULL,
        fecha DATE NULL,
        duracion_min DECIMAL(6,2) NULL,
        vistas INT NOT NULL CONSTRAINT DF_sesiones_vistas DEFAULT 0,
        CONSTRAINT FK_sesiones_usuarios FOREIGN KEY(usuario_id)
            REFERENCES dbo.usuarios(id) ON DELETE CASCADE,
        CONSTRAINT CK_sesiones_duracion CHECK (duracion_min IS NULL OR duracion_min >= 0),
        CONSTRAINT CK_sesiones_vistas CHECK (vistas >= 0)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_interacciones_usuario_fecha' AND object_id=OBJECT_ID('dbo.interacciones'))
    CREATE INDEX IX_interacciones_usuario_fecha ON dbo.interacciones(usuario_id,fecha);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name='IX_sesiones_usuario_fecha' AND object_id=OBJECT_ID('dbo.sesiones'))
    CREATE INDEX IX_sesiones_usuario_fecha ON dbo.sesiones(usuario_id,fecha);
GO
PRINT 'Esquema NEXUS_OLTP verificado/creado.';
GO
