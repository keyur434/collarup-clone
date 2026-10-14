-- Magic-link access for hiring managers / interviewers (including directory-only contacts).

IF OBJECT_ID(N'interview_report_shares', N'U') IS NULL
CREATE TABLE interview_report_shares (
    id INT IDENTITY(1,1) PRIMARY KEY,
    report_id CHAR(36) NOT NULL,
    recipient_email NVARCHAR(255) NOT NULL,
    recipient_role NVARCHAR(20) NOT NULL CHECK (recipient_role IN ('hiring_manager','interviewer')),
    access_token CHAR(64) NOT NULL,
    view_count INT NOT NULL DEFAULT 0,
    last_viewed_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_irs_report FOREIGN KEY (report_id) REFERENCES interview_reports(id) ON DELETE CASCADE,
    CONSTRAINT UQ_irs_report_email UNIQUE (report_id, recipient_email),
    CONSTRAINT UQ_irs_token UNIQUE (access_token)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_irs_report' AND object_id = OBJECT_ID(N'interview_report_shares'))
    CREATE INDEX IX_irs_report ON interview_report_shares(report_id);
GO
