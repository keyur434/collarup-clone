-- Phase 2: notifications, settings, Keka cache, integrity

IF OBJECT_ID(N'app_settings', N'U') IS NULL
CREATE TABLE app_settings (
    setting_key NVARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value NVARCHAR(MAX) NULL,
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'retention_days_completed')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('retention_days_completed', '365');
IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'retention_auto_archive')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('retention_auto_archive', '1');
IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'notify_report_ready')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('notify_report_ready', '1');
IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'notify_decision_email')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('notify_decision_email', '1');
GO

IF OBJECT_ID(N'notification_log', N'U') IS NULL
CREATE TABLE notification_log (
    id INT IDENTITY(1,1) PRIMARY KEY,
    notification_type NVARCHAR(50) NOT NULL,
    recipient NVARCHAR(255) NOT NULL,
    subject NVARCHAR(500) NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending',
    error_message NVARCHAR(MAX) NULL,
    meta_json NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF OBJECT_ID(N'keka_employees', N'U') IS NULL
CREATE TABLE keka_employees (
    id INT IDENTITY(1,1) PRIMARY KEY,
    keka_id NVARCHAR(100) NOT NULL,
    employee_number NVARCHAR(50) NULL,
    email NVARCHAR(255) NULL,
    first_name NVARCHAR(100) NULL,
    last_name NVARCHAR(100) NULL,
    job_title NVARCHAR(255) NULL,
    department NVARCHAR(255) NULL,
    employment_status NVARCHAR(50) NULL,
    raw_json NVARCHAR(MAX) NULL,
    synced_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_keka_employee UNIQUE (keka_id)
);
GO

IF COL_LENGTH('interview_media', 'processing_status') IS NULL
    ALTER TABLE interview_media ADD processing_status NVARCHAR(30) NULL;
GO

IF COL_LENGTH('interview_media', 'storage_provider') IS NULL
    ALTER TABLE interview_media ADD storage_provider NVARCHAR(30) NULL;
GO

IF COL_LENGTH('interview_media', 'drive_item_id') IS NULL
    ALTER TABLE interview_media ADD drive_item_id NVARCHAR(200) NULL;
GO

IF COL_LENGTH('interview_media', 'drive_path') IS NULL
    ALTER TABLE interview_media ADD drive_path NVARCHAR(500) NULL;
GO
