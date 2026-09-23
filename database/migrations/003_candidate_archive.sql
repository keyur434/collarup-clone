IF COL_LENGTH('applications', 'is_archived') IS NULL
    ALTER TABLE applications ADD is_archived BIT NOT NULL DEFAULT 0;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_applications_archived' AND object_id = OBJECT_ID('applications'))
    CREATE INDEX IX_applications_archived ON applications (is_archived, job_id);
GO
