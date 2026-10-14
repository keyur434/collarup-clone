-- Hiring desk preview: resume cache, HR notes, pool rank storage
-- FKs use NO ACTION on application paths to avoid SQL Server multiple-cascade errors.
IF OBJECT_ID(N'cv_summaries', N'U') IS NULL
BEGIN
    CREATE TABLE cv_summaries (
        id CHAR(36) NOT NULL PRIMARY KEY,
        candidate_id CHAR(36) NOT NULL,
        application_id CHAR(36) NULL,
        source_file_hash CHAR(64) NOT NULL,
        extracted_text NVARCHAR(MAX) NULL,
        summary_json NVARCHAR(MAX) NULL,
        model_name NVARCHAR(120) NULL,
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_cvs_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE NO ACTION,
        CONSTRAINT FK_cvs_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE NO ACTION
    );
    CREATE INDEX IX_cv_summaries_app ON cv_summaries(application_id);
END
GO

IF OBJECT_ID(N'hiring_desk_decision_notes', N'U') IS NULL
BEGIN
    CREATE TABLE hiring_desk_decision_notes (
        id INT IDENTITY(1,1) PRIMARY KEY,
        job_id CHAR(36) NOT NULL,
        application_id CHAR(36) NOT NULL,
        user_id INT NOT NULL,
        note_type NVARCHAR(40) NOT NULL,
        ai_suggested_rank INT NULL,
        hr_rank INT NULL,
        reason NVARCHAR(MAX) NOT NULL,
        created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_hddn_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
        CONSTRAINT FK_hddn_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE NO ACTION,
        CONSTRAINT FK_hddn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE NO ACTION
    );
END
GO

IF COL_LENGTH('job_hiring_insights', 'pool_compare_json') IS NULL
BEGIN
    ALTER TABLE job_hiring_insights ADD pool_compare_json NVARCHAR(MAX) NULL;
END
GO
