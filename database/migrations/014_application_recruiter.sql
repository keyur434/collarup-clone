USE collarup_clone;
GO

IF COL_LENGTH('applications', 'recruiter_id') IS NULL
    ALTER TABLE applications ADD recruiter_id INT NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_applications_recruiter'
)
BEGIN
    ALTER TABLE applications ADD CONSTRAINT FK_applications_recruiter
        FOREIGN KEY (recruiter_id) REFERENCES users(id);
END
GO

UPDATE a
SET recruiter_id = j.created_by
FROM applications a
INNER JOIN jobs j ON j.id = a.job_id
WHERE a.recruiter_id IS NULL AND j.created_by IS NOT NULL;
GO
