/* NEXUS - Data Warehouse SQL Server/Azure SQL
   Objetos NX_ compatibles con el notebook NEXUS_BI_Azure_SQL.
   Ejecutar en la base NEXUS. No elimina ni trunca objetos existentes.
*/
USE [NEXUS];
GO
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO
IF OBJECT_ID('dbo.NX_DimLocation','U') IS NULL
CREATE TABLE dbo.NX_DimLocation (
    LocationKey INT IDENTITY(1,1) CONSTRAINT PK_NX_DimLocation PRIMARY KEY,
    Country NVARCHAR(80) NULL,
    Department NVARCHAR(80) NULL,
    Province NVARCHAR(80) NULL,
    District NVARCHAR(100) NULL,
    Address NVARCHAR(250) NULL,
    CONSTRAINT UQ_NX_DimLocation UNIQUE(Country,Department,Province,District,Address)
);
GO
IF OBJECT_ID('dbo.NX_DimUser','U') IS NULL
CREATE TABLE dbo.NX_DimUser (
    UserKey INT IDENTITY(1,1) CONSTRAINT PK_NX_DimUser PRIMARY KEY,
    SourceUserID INT NOT NULL CONSTRAINT UQ_NX_DimUser_Source UNIQUE,
    DNI NVARCHAR(20) NULL,
    FullName NVARCHAR(200) NULL,
    Gender NVARCHAR(50) NULL,
    Email NVARCHAR(200) NULL,
    RiskLevel INT NULL,
    RiskCategory NVARCHAR(30) NULL,
    Status NVARCHAR(40) NULL,
    RegistrationDate DATE NULL,
    LocationKey INT NULL,
    CONSTRAINT FK_NX_DimUser_Location FOREIGN KEY(LocationKey) REFERENCES dbo.NX_DimLocation(LocationKey),
    CONSTRAINT CK_NX_DimUser_Risk CHECK (RiskLevel IS NULL OR RiskLevel BETWEEN 1 AND 5)
);
GO
IF OBJECT_ID('dbo.NX_DimDevice','U') IS NULL
CREATE TABLE dbo.NX_DimDevice (
    DeviceKey INT IDENTITY(1,1) CONSTRAINT PK_NX_DimDevice PRIMARY KEY,
    Device NVARCHAR(80) NULL,
    OperatingSystem NVARCHAR(80) NULL,
    CONSTRAINT UQ_NX_DimDevice UNIQUE(Device,OperatingSystem)
);
GO
IF OBJECT_ID('dbo.NX_DimInteraction','U') IS NULL
CREATE TABLE dbo.NX_DimInteraction (
    InteractionKey INT IDENTITY(1,1) CONSTRAINT PK_NX_DimInteraction PRIMARY KEY,
    InteractionType NVARCHAR(80) NULL,
    Category NVARCHAR(100) NULL,
    Format NVARCHAR(50) NULL,
    CONSTRAINT UQ_NX_DimInteraction UNIQUE(InteractionType,Category,Format)
);
GO
IF OBJECT_ID('dbo.NX_DimDate','U') IS NULL
CREATE TABLE dbo.NX_DimDate (
    DateKey INT NOT NULL CONSTRAINT PK_NX_DimDate PRIMARY KEY,
    FullDate DATE NOT NULL CONSTRAINT UQ_NX_DimDate_FullDate UNIQUE,
    YearNum INT NULL,
    MonthNum INT NULL,
    DayNum INT NULL
);
GO
IF OBJECT_ID('dbo.NX_FactInteraction','U') IS NULL
CREATE TABLE dbo.NX_FactInteraction (
    FactKey BIGINT IDENTITY(1,1) CONSTRAINT PK_NX_FactInteraction PRIMARY KEY,
    SourceInteractionID INT NULL CONSTRAINT UQ_NX_FactInteraction_Source UNIQUE,
    UserKey INT NULL,
    DateKey INT NULL,
    DeviceKey INT NULL,
    InteractionKey INT NULL,
    DurationSeconds INT NULL,
    Views INT NULL,
    CONSTRAINT FK_NX_FI_User FOREIGN KEY(UserKey) REFERENCES dbo.NX_DimUser(UserKey),
    CONSTRAINT FK_NX_FI_Date FOREIGN KEY(DateKey) REFERENCES dbo.NX_DimDate(DateKey),
    CONSTRAINT FK_NX_FI_Device FOREIGN KEY(DeviceKey) REFERENCES dbo.NX_DimDevice(DeviceKey),
    CONSTRAINT FK_NX_FI_Interaction FOREIGN KEY(InteractionKey) REFERENCES dbo.NX_DimInteraction(InteractionKey)
);
GO
IF OBJECT_ID('dbo.NX_FactSession','U') IS NULL
CREATE TABLE dbo.NX_FactSession (
    FactSessionKey BIGINT IDENTITY(1,1) CONSTRAINT PK_NX_FactSession PRIMARY KEY,
    SourceSessionID INT NULL CONSTRAINT UQ_NX_FactSession_Source UNIQUE,
    UserKey INT NULL,
    DateKey INT NULL,
    DurationMinutes DECIMAL(10,2) NULL,
    Views INT NULL,
    CONSTRAINT FK_NX_FS_User FOREIGN KEY(UserKey) REFERENCES dbo.NX_DimUser(UserKey),
    CONSTRAINT FK_NX_FS_Date FOREIGN KEY(DateKey) REFERENCES dbo.NX_DimDate(DateKey)
);
GO
IF OBJECT_ID('dbo.NX_PredictionRisk','U') IS NULL
CREATE TABLE dbo.NX_PredictionRisk (
    PredictionKey BIGINT IDENTITY(1,1) CONSTRAINT PK_NX_PredictionRisk PRIMARY KEY,
    SourceUserID INT NOT NULL,
    PredictedRisk INT NOT NULL,
    RiskLabel NVARCHAR(30) NULL,
    ModelName NVARCHAR(100) NULL,
    RunAt DATETIME2 NOT NULL CONSTRAINT DF_NX_PredictionRisk_RunAt DEFAULT SYSUTCDATETIME(),
    CONSTRAINT CK_NX_PredictionRisk_Risk CHECK (PredictedRisk BETWEEN 1 AND 5)
);
GO
IF OBJECT_ID('dbo.NX_ETLLog','U') IS NULL
CREATE TABLE dbo.NX_ETLLog (
    RunID BIGINT IDENTITY(1,1) CONSTRAINT PK_NX_ETLLog PRIMARY KEY,
    StartedAt DATETIME2 NOT NULL CONSTRAINT DF_NX_ETLLog_StartedAt DEFAULT SYSUTCDATETIME(),
    CompletedAt DATETIME2 NULL,
    SourceUsers INT NULL,
    SourceInteractions INT NULL,
    SourceSessions INT NULL,
    Status NVARCHAR(30) NULL,
    Details NVARCHAR(2000) NULL
);
GO
PRINT 'Esquema NEXUS DW verificado/creado.';
GO
