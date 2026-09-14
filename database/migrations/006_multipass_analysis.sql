-- Phase 1: multi-pass AI analysis audit trail

IF OBJECT_ID(N'ai_analysis_passes', N'U') IS NULL
CREATE TABLE ai_analysis_passes (
    id INT IDENTITY(1,1) PRIMARY KEY,
    run_id INT NOT NULL,
    interview_id CHAR(36) NOT NULL,
    pass_name NVARCHAR(50) NOT NULL,
    pass_order INT NOT NULL DEFAULT 0,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','processing','completed','failed')),
    model_name NVARCHAR(100) NULL,
    prompt_version NVARCHAR(20) NOT NULL DEFAULT 'v2-multipass',
    output_json NVARCHAR(MAX) NULL,
    error_message NVARCHAR(MAX) NULL,
    started_at DATETIME2 NULL,
    completed_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_aap_run FOREIGN KEY (run_id) REFERENCES ai_analysis_runs(id) ON DELETE CASCADE,
    CONSTRAINT FK_aap_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE NO ACTION
);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'IX_ai_analysis_passes_interview' AND object_id = OBJECT_ID(N'ai_analysis_passes')
)
CREATE INDEX IX_ai_analysis_passes_interview ON ai_analysis_passes(interview_id, pass_order);
GO
