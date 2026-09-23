USE collarup_clone;
GO

IF OBJECT_ID(N'skill_tags', N'U') IS NULL
CREATE TABLE skill_tags (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT UQ_skill_tags_name UNIQUE (name)
);
GO

IF OBJECT_ID(N'job_external_contacts', N'U') IS NULL
CREATE TABLE job_external_contacts (
    id INT IDENTITY(1,1) PRIMARY KEY,
    job_id CHAR(36) NOT NULL,
    contact_role NVARCHAR(20) NOT NULL CHECK (contact_role IN ('hiring_manager','interviewer')),
    name NVARCHAR(200) NOT NULL,
    email NVARCHAR(255) NULL,
    CONSTRAINT FK_jec_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
GO

IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'company_name')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('company_name', N'Your Company');
IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'company_about')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('company_about', N'');
IF NOT EXISTS (SELECT 1 FROM app_settings WHERE setting_key = 'notify_interview_invite')
    INSERT INTO app_settings (setting_key, setting_value) VALUES ('notify_interview_invite', '1');
GO

IF NOT EXISTS (SELECT 1 FROM candidate_sources WHERE name = N'Naukri')
    INSERT INTO candidate_sources (name) VALUES (N'Naukri');
GO

IF NOT EXISTS (SELECT 1 FROM skill_tags)
INSERT INTO skill_tags (name, sort_order) VALUES
(N'Communication', 1), (N'Leadership', 2), (N'Problem Solving', 3), (N'JavaScript', 4),
(N'PHP', 5), (N'SQL', 6), (N'Python', 7), (N'Project Management', 8), (N'Sales', 9),
(N'Microsoft Excel', 10), (N'Negotiation', 11), (N'Team Management', 12);
GO
