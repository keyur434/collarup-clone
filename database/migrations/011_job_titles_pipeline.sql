USE collarup_clone;
GO

IF OBJECT_ID(N'job_titles', N'U') IS NULL
CREATE TABLE job_titles (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BIT NOT NULL DEFAULT 1
);
GO

IF NOT EXISTS (SELECT 1 FROM job_titles)
INSERT INTO job_titles (name, sort_order) VALUES
(N'GET', 1), (N'ET', 2), (N'AM', 3), (N'SAM', 4), (N'AGM', 5), (N'DGM', 6), (N'GM', 7),
(N'AVP', 8), (N'VP', 9), (N'SVP', 10), (N'Head', 11), (N'Manager', 12), (N'Sr. Manager', 13),
(N'Executive', 14), (N'Sr. Executive', 15), (N'Officer', 16), (N'Engineer', 17), (N'Sr. Engineer', 18);
GO

IF COL_LENGTH('jobs', 'job_title_id') IS NULL
    ALTER TABLE jobs ADD job_title_id INT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_jobs_job_title')
    ALTER TABLE jobs ADD CONSTRAINT FK_jobs_job_title FOREIGN KEY (job_title_id) REFERENCES job_titles(id);
GO
