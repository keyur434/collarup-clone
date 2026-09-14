-- Migration: OneDrive storage, Azure AD SSO, pending recordings
-- Run via: php database/migrate.php

IF COL_LENGTH('users', 'azure_oid') IS NULL
    ALTER TABLE users ADD azure_oid NVARCHAR(64) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_users_azure_oid' AND object_id = OBJECT_ID('users'))
    CREATE UNIQUE INDEX IX_users_azure_oid ON users(azure_oid) WHERE azure_oid IS NOT NULL;
GO

IF COL_LENGTH('interview_media', 'storage_provider') IS NULL
    ALTER TABLE interview_media ADD storage_provider NVARCHAR(20) NOT NULL DEFAULT 'local';
GO

IF COL_LENGTH('interview_media', 'drive_item_id') IS NULL
    ALTER TABLE interview_media ADD drive_item_id NVARCHAR(200) NULL;
GO

IF COL_LENGTH('interview_media', 'drive_path') IS NULL
    ALTER TABLE interview_media ADD drive_path NVARCHAR(500) NULL;
GO

IF COL_LENGTH('interview_media', 'site_id') IS NULL
    ALTER TABLE interview_media ADD site_id NVARCHAR(200) NULL;
GO

IF COL_LENGTH('interview_media', 'processing_status') IS NULL
    ALTER TABLE interview_media ADD processing_status NVARCHAR(30) NOT NULL DEFAULT 'pending';
GO

IF OBJECT_ID(N'pending_recordings', N'U') IS NULL
CREATE TABLE pending_recordings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    drive_item_id NVARCHAR(200) NOT NULL,
    drive_path NVARCHAR(500) NULL,
    original_filename NVARCHAR(255) NULL,
    file_size_bytes BIGINT NULL,
    duration_seconds INT NULL,
    meeting_url NVARCHAR(1000) NULL,
    meeting_subject NVARCHAR(500) NULL,
    recorded_at DATETIME2 NULL,
    status NVARCHAR(30) NOT NULL DEFAULT 'pending_assignment'
        CHECK (status IN ('pending_assignment','assigned','processing','ready','failed')),
    interview_id CHAR(36) NULL,
    assigned_by INT NULL,
    assigned_at DATETIME2 NULL,
    error_message NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_pr_interview FOREIGN KEY (interview_id) REFERENCES interviews(id),
    CONSTRAINT FK_pr_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'ingestion_watermarks', N'U') IS NULL
CREATE TABLE ingestion_watermarks (
    id INT IDENTITY(1,1) PRIMARY KEY,
    source_key NVARCHAR(100) NOT NULL UNIQUE,
    delta_link NVARCHAR(MAX) NULL,
    last_synced_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF NOT EXISTS (SELECT 1 FROM ingestion_watermarks WHERE source_key = 'onedrive_recordings')
    INSERT INTO ingestion_watermarks (source_key) VALUES ('onedrive_recordings');
GO
