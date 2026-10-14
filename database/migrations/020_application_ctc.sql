IF COL_LENGTH('applications', 'current_ctc') IS NULL
    ALTER TABLE applications ADD current_ctc NVARCHAR(120) NULL;
GO
IF COL_LENGTH('applications', 'expected_ctc') IS NULL
    ALTER TABLE applications ADD expected_ctc NVARCHAR(120) NULL;
GO
