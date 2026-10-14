-- AI-generated comparative hiring brief (preview hiring desk only)
IF OBJECT_ID(N'job_hiring_insights', N'U') IS NULL
BEGIN
    CREATE TABLE job_hiring_insights (
        job_id CHAR(36) NOT NULL PRIMARY KEY,
        input_hash CHAR(64) NOT NULL,
        prompt_version NVARCHAR(40) NOT NULL,
        model_name NVARCHAR(120) NULL,
        insights_json NVARCHAR(MAX) NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'completed',
        error_message NVARCHAR(MAX) NULL,
        generated_by INT NULL,
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_jhi_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
        CONSTRAINT FK_jhi_user FOREIGN KEY (generated_by) REFERENCES users(id)
    );
END
GO
