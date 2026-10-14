-- Optional config for hiring desk preview (weights/minimums). Safe to run; unused until feature ships in desk UI.
IF OBJECT_ID(N'job_hiring_desk_config', N'U') IS NULL
BEGIN
    CREATE TABLE job_hiring_desk_config (
        job_id CHAR(36) NOT NULL PRIMARY KEY,
        weights_json NVARCHAR(MAX) NULL,
        minimums_json NVARCHAR(MAX) NULL,
        updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_jhdc_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
    );
END
GO
