-- CollarUp Clone - Microsoft SQL Server Schema
-- SQL Server 2016+ recommended (JSON stored as NVARCHAR(MAX))

IF DB_ID(N'collarup_clone') IS NULL
    CREATE DATABASE collarup_clone;
GO

USE collarup_clone;
GO

-- Master data
IF OBJECT_ID(N'departments', N'U') IS NULL
CREATE TABLE departments (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF OBJECT_ID(N'seniority_levels', N'U') IS NULL
CREATE TABLE seniority_levels (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
);
GO

IF OBJECT_ID(N'countries', N'U') IS NULL
CREATE TABLE countries (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL,
    code NVARCHAR(3) NOT NULL
);
GO

IF OBJECT_ID(N'states', N'U') IS NULL
CREATE TABLE states (
    id INT IDENTITY(1,1) PRIMARY KEY,
    country_id INT NOT NULL,
    name NVARCHAR(100) NOT NULL,
    CONSTRAINT FK_states_country FOREIGN KEY (country_id) REFERENCES countries(id)
);
GO

IF OBJECT_ID(N'cities', N'U') IS NULL
CREATE TABLE cities (
    id INT IDENTITY(1,1) PRIMARY KEY,
    state_id INT NOT NULL,
    name NVARCHAR(100) NOT NULL,
    CONSTRAINT FK_cities_state FOREIGN KEY (state_id) REFERENCES states(id)
);
GO

IF OBJECT_ID(N'work_modes', N'U') IS NULL
CREATE TABLE work_modes (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(50) NOT NULL
);
GO

IF OBJECT_ID(N'candidate_sources', N'U') IS NULL
CREATE TABLE candidate_sources (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(100) NOT NULL
);
GO

IF OBJECT_ID(N'experience_bands', N'U') IS NULL
CREATE TABLE experience_bands (
    id INT IDENTITY(1,1) PRIMARY KEY,
    name NVARCHAR(50) NOT NULL,
    min_years DECIMAL(4,1) NOT NULL DEFAULT 0,
    max_years DECIMAL(4,1) NULL,
    sort_order INT NOT NULL DEFAULT 0
);
GO

-- Users
IF OBJECT_ID(N'users', N'U') IS NULL
CREATE TABLE users (
    id INT IDENTITY(1,1) PRIMARY KEY,
    first_name NVARCHAR(100) NOT NULL,
    last_name NVARCHAR(100) NOT NULL,
    email NVARCHAR(255) NOT NULL UNIQUE,
    password_hash NVARCHAR(255) NOT NULL,
    role NVARCHAR(20) NOT NULL DEFAULT 'member' CHECK (role IN ('owner','hr_head','member')),
    initials NVARCHAR(5) NULL,
    is_active BIT NOT NULL DEFAULT 1,
    last_login_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

-- Jobs
IF OBJECT_ID(N'jobs', N'U') IS NULL
CREATE TABLE jobs (
    id CHAR(36) NOT NULL PRIMARY KEY,
    title NVARCHAR(255) NOT NULL,
    department_id INT NULL,
    seniority_id INT NULL,
    country_id INT NULL,
    state_id INT NULL,
    city_id INT NULL,
    num_hires INT NOT NULL DEFAULT 1,
    work_mode_id INT NULL,
    about_company NVARCHAR(MAX) NULL,
    key_responsibility NVARCHAR(MAX) NULL,
    qualifications NVARCHAR(MAX) NULL,
    must_have_criteria NVARCHAR(MAX) NULL,
    key_skills NVARCHAR(MAX) NULL,
    salary_currency NVARCHAR(5) NOT NULL DEFAULT 'INR',
    salary_min DECIMAL(12,2) NULL,
    salary_max DECIMAL(12,2) NULL,
    salary_period NVARCHAR(10) NOT NULL DEFAULT 'monthly' CHECK (salary_period IN ('monthly','annual')),
    hide_salary BIT NOT NULL DEFAULT 0,
    status NVARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive','archived')),
    created_by INT NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_jobs_department FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT FK_jobs_seniority FOREIGN KEY (seniority_id) REFERENCES seniority_levels(id),
    CONSTRAINT FK_jobs_country FOREIGN KEY (country_id) REFERENCES countries(id),
    CONSTRAINT FK_jobs_state FOREIGN KEY (state_id) REFERENCES states(id),
    CONSTRAINT FK_jobs_city FOREIGN KEY (city_id) REFERENCES cities(id),
    CONSTRAINT FK_jobs_work_mode FOREIGN KEY (work_mode_id) REFERENCES work_modes(id),
    CONSTRAINT FK_jobs_created_by FOREIGN KEY (created_by) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'job_hiring_managers', N'U') IS NULL
CREATE TABLE job_hiring_managers (
    job_id CHAR(36) NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (job_id, user_id),
    CONSTRAINT FK_jhm_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT FK_jhm_user FOREIGN KEY (user_id) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'job_interviewers', N'U') IS NULL
CREATE TABLE job_interviewers (
    job_id CHAR(36) NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (job_id, user_id),
    CONSTRAINT FK_ji_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT FK_ji_user FOREIGN KEY (user_id) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'job_pipeline_stages', N'U') IS NULL
CREATE TABLE job_pipeline_stages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    job_id CHAR(36) NOT NULL,
    name NVARCHAR(150) NOT NULL,
    stage_type NVARCHAR(20) NOT NULL DEFAULT 'round' CHECK (stage_type IN ('applicant_pool','ai_interview','round','offer')),
    sort_order INT NOT NULL DEFAULT 0,
    is_system BIT NOT NULL DEFAULT 0,
    is_active BIT NOT NULL DEFAULT 1,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_jps_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'job_interview_questions', N'U') IS NULL
CREATE TABLE job_interview_questions (
    id INT IDENTITY(1,1) PRIMARY KEY,
    job_id CHAR(36) NOT NULL,
    question_text NVARCHAR(MAX) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_jiq_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'job_rubric_criteria', N'U') IS NULL
CREATE TABLE job_rubric_criteria (
    id INT IDENTITY(1,1) PRIMARY KEY,
    job_id CHAR(36) NOT NULL,
    name NVARCHAR(150) NOT NULL,
    description NVARCHAR(MAX) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active BIT NOT NULL DEFAULT 1,
    CONSTRAINT FK_jrc_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'job_email_templates', N'U') IS NULL
CREATE TABLE job_email_templates (
    id INT IDENTITY(1,1) PRIMARY KEY,
    job_id CHAR(36) NOT NULL,
    template_type NVARCHAR(20) NOT NULL CHECK (template_type IN ('invited','rejected')),
    subject NVARCHAR(255) NULL,
    body_html NVARCHAR(MAX) NOT NULL,
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_job_template UNIQUE (job_id, template_type),
    CONSTRAINT FK_jet_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
GO

-- Candidates
IF OBJECT_ID(N'candidates', N'U') IS NULL
CREATE TABLE candidates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    first_name NVARCHAR(100) NOT NULL,
    last_name NVARCHAR(100) NOT NULL,
    email NVARCHAR(255) NOT NULL,
    phone_country_code NVARCHAR(10) NOT NULL DEFAULT '+91',
    phone NVARCHAR(20) NULL,
    resume_path NVARCHAR(500) NULL,
    resume_blob_name NVARCHAR(500) NULL,
    years_experience DECIMAL(4,1) NULL,
    current_company NVARCHAR(255) NULL,
    current_location NVARCHAR(255) NULL,
    source_id INT NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_candidates_source FOREIGN KEY (source_id) REFERENCES candidate_sources(id)
);
GO

IF OBJECT_ID(N'applications', N'U') IS NULL
CREATE TABLE applications (
    id CHAR(36) NOT NULL PRIMARY KEY,
    candidate_id CHAR(36) NOT NULL,
    job_id CHAR(36) NOT NULL,
    current_stage_id CHAR(36) NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('contacted','pending','scheduled','processing','ongoing','incomplete','completed','cancelled')),
    decision NVARCHAR(20) NOT NULL DEFAULT 'none' CHECK (decision IN ('none','advance','hold','reject','offer')),
    match_score_grade NVARCHAR(1) NULL CHECK (match_score_grade IN ('A','B','C')),
    application_date DATE NULL,
    is_archived BIT NOT NULL DEFAULT 0,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_candidate_job UNIQUE (candidate_id, job_id),
    CONSTRAINT FK_app_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    CONSTRAINT FK_app_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    CONSTRAINT FK_app_stage FOREIGN KEY (current_stage_id) REFERENCES job_pipeline_stages(id)
);
GO

-- Interviews
IF OBJECT_ID(N'interviews', N'U') IS NULL
CREATE TABLE interviews (
    id CHAR(36) NOT NULL PRIMARY KEY,
    application_id CHAR(36) NOT NULL,
    stage_id CHAR(36) NOT NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','scheduled','processing','ongoing','incomplete','completed','cancelled')),
    scheduled_date DATE NULL,
    scheduled_start TIME NULL,
    scheduled_end TIME NULL,
    completed_at DATETIME2 NULL,
    meeting_url NVARCHAR(1000) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_interviews_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT FK_interviews_stage FOREIGN KEY (stage_id) REFERENCES job_pipeline_stages(id)
);
GO

IF OBJECT_ID(N'interview_media', N'U') IS NULL
CREATE TABLE interview_media (
    id INT IDENTITY(1,1) PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    media_type NVARCHAR(20) NOT NULL DEFAULT 'upload' CHECK (media_type IN ('upload','zoom','meet','teams','external')),
    original_filename NVARCHAR(255) NULL,
    blob_container NVARCHAR(100) NULL,
    blob_name NVARCHAR(500) NULL,
    file_size_bytes BIGINT NULL,
    duration_seconds INT NULL,
    compression_profile NVARCHAR(50) NOT NULL DEFAULT 'h264_720p_1mbps',
    external_url NVARCHAR(1000) NULL,
    playback_mode NVARCHAR(10) NOT NULL DEFAULT 'blob' CHECK (playback_mode IN ('blob','embed')),
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_im_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'transcripts', N'U') IS NULL
CREATE TABLE transcripts (
    id INT IDENTITY(1,1) PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    full_text NVARCHAR(MAX) NULL,
    segments NVARCHAR(MAX) NULL,
    provider NVARCHAR(50) NOT NULL DEFAULT 'elevenlabs',
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_transcripts_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'ai_analysis_runs', N'U') IS NULL
CREATE TABLE ai_analysis_runs (
    id INT IDENTITY(1,1) PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','processing','completed','failed')),
    model_name NVARCHAR(100) NULL,
    prompt_version NVARCHAR(20) NOT NULL DEFAULT 'v1',
    error_message NVARCHAR(MAX) NULL,
    started_at DATETIME2 NULL,
    completed_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_aar_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
GO

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

IF OBJECT_ID(N'interview_reports', N'U') IS NULL
CREATE TABLE interview_reports (
    id CHAR(36) NOT NULL PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    overall_score DECIMAL(4,1) NULL,
    experience_score DECIMAL(4,1) NULL,
    culture_score DECIMAL(4,1) NULL,
    soft_skills_score DECIMAL(4,1) NULL,
    score_label NVARCHAR(20) NULL,
    overview_strengths NVARCHAR(MAX) NULL,
    overview_weaknesses NVARCHAR(MAX) NULL,
    overview_red_flags NVARCHAR(MAX) NULL,
    overview_team_fit NVARCHAR(MAX) NULL,
    overview_cheating_detection NVARCHAR(MAX) NULL,
    overview_qualification_match NVARCHAR(MAX) NULL,
    overview_logistics NVARCHAR(MAX) NULL,
    overview_follow_up NVARCHAR(MAX) NULL,
    overview_recommendation NVARCHAR(MAX) NULL,
    raw_analysis_json NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_ir_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'interview_criterion_scores', N'U') IS NULL
CREATE TABLE interview_criterion_scores (
    id INT IDENTITY(1,1) PRIMARY KEY,
    report_id CHAR(36) NOT NULL,
    criterion_id INT NULL,
    criterion_name NVARCHAR(150) NOT NULL,
    score DECIMAL(4,1) NOT NULL,
    CONSTRAINT FK_ics_report FOREIGN KEY (report_id) REFERENCES interview_reports(id) ON DELETE CASCADE,
    CONSTRAINT FK_ics_criterion FOREIGN KEY (criterion_id) REFERENCES job_rubric_criteria(id)
);
GO

IF OBJECT_ID(N'interview_qa_notes', N'U') IS NULL
CREATE TABLE interview_qa_notes (
    id INT IDENTITY(1,1) PRIMARY KEY,
    report_id CHAR(36) NOT NULL,
    question_text NVARCHAR(MAX) NOT NULL,
    answer_summary NVARCHAR(MAX) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT FK_iqn_report FOREIGN KEY (report_id) REFERENCES interview_reports(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'integrity_assessments', N'U') IS NULL
CREATE TABLE integrity_assessments (
    id INT IDENTITY(1,1) PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    cheating_likelihood NVARCHAR(10) NOT NULL DEFAULT 'low' CHECK (cheating_likelihood IN ('low','medium','high')),
    external_display NVARCHAR(20) NOT NULL DEFAULT 'na' CHECK (external_display IN ('not_detected','detected','na')),
    window_switching INT NOT NULL DEFAULT 0,
    content_copied NVARCHAR(20) NOT NULL DEFAULT 'na' CHECK (content_copied IN ('not_detected','detected','na')),
    ai_answer_patterns NVARCHAR(20) NOT NULL DEFAULT 'na' CHECK (ai_answer_patterns IN ('not_detected','detected','na')),
    notes NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_ia_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'integrity_events', N'U') IS NULL
CREATE TABLE integrity_events (
    id INT IDENTITY(1,1) PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    event_type NVARCHAR(50) NOT NULL,
    event_data NVARCHAR(MAX) NULL,
    occurred_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_ie_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE
);
GO

IF OBJECT_ID(N'team_chat_messages', N'U') IS NULL
CREATE TABLE team_chat_messages (
    id INT IDENTITY(1,1) PRIMARY KEY,
    interview_id CHAR(36) NOT NULL,
    user_id INT NOT NULL,
    message_text NVARCHAR(MAX) NOT NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_tcm_interview FOREIGN KEY (interview_id) REFERENCES interviews(id) ON DELETE CASCADE,
    CONSTRAINT FK_tcm_user FOREIGN KEY (user_id) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'activity_logs', N'U') IS NULL
CREATE TABLE activity_logs (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    user_id INT NULL,
    entity_type NVARCHAR(50) NOT NULL,
    entity_id NVARCHAR(36) NOT NULL,
    action NVARCHAR(50) NOT NULL,
    meta NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_al_user FOREIGN KEY (user_id) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'job_queue', N'U') IS NULL
CREATE TABLE job_queue (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    queue_name NVARCHAR(50) NOT NULL DEFAULT 'default',
    job_type NVARCHAR(100) NOT NULL,
    payload NVARCHAR(MAX) NOT NULL,
    status NVARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','processing','completed','failed')),
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    error_message NVARCHAR(MAX) NULL,
    available_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    started_at DATETIME2 NULL,
    completed_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

CREATE NONCLUSTERED INDEX IX_job_queue_status ON job_queue (queue_name, status, available_at);
GO

IF COL_LENGTH('users', 'azure_oid') IS NULL
    ALTER TABLE users ADD azure_oid NVARCHAR(64) NULL;
GO

IF COL_LENGTH('interview_media', 'storage_provider') IS NULL
    ALTER TABLE interview_media ADD storage_provider NVARCHAR(20) NOT NULL DEFAULT 'local';
GO

IF COL_LENGTH('interview_media', 'drive_item_id') IS NULL
    ALTER TABLE interview_media ADD drive_item_id NVARCHAR(200) NULL;
GO

IF COL_LENGTH('interview_media', 'drive_path') IS NULL
    ALTER TABLE interview_media ADD drive_path NVARCHAR(500) NULL;
GO

IF COL_LENGTH('interview_media', 'site_id') IS NULL
    ALTER TABLE interview_media ADD site_id NVARCHAR(200) NULL;
GO

IF COL_LENGTH('interview_media', 'processing_status') IS NULL
    ALTER TABLE interview_media ADD processing_status NVARCHAR(30) NOT NULL DEFAULT 'pending';
GO

IF OBJECT_ID(N'pending_recordings', N'U') IS NULL
CREATE TABLE pending_recordings (
    id CHAR(36) NOT NULL PRIMARY KEY,
    drive_item_id NVARCHAR(200) NOT NULL,
    drive_path NVARCHAR(500) NULL,
    original_filename NVARCHAR(255) NULL,
    file_size_bytes BIGINT NULL,
    duration_seconds INT NULL,
    meeting_url NVARCHAR(1000) NULL,
    meeting_subject NVARCHAR(500) NULL,
    recorded_at DATETIME2 NULL,
    status NVARCHAR(30) NOT NULL DEFAULT 'pending_assignment'
        CHECK (status IN ('pending_assignment','assigned','processing','ready','failed')),
    interview_id CHAR(36) NULL,
    assigned_by INT NULL,
    assigned_at DATETIME2 NULL,
    error_message NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_pr_interview FOREIGN KEY (interview_id) REFERENCES interviews(id),
    CONSTRAINT FK_pr_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id)
);
GO

IF OBJECT_ID(N'ingestion_watermarks', N'U') IS NULL
CREATE TABLE ingestion_watermarks (
    id INT IDENTITY(1,1) PRIMARY KEY,
    source_key NVARCHAR(100) NOT NULL UNIQUE,
    delta_link NVARCHAR(MAX) NULL,
    last_synced_at DATETIME2 NULL,
    created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF NOT EXISTS (SELECT 1 FROM ingestion_watermarks WHERE source_key = 'onedrive_recordings')
    INSERT INTO ingestion_watermarks (source_key) VALUES ('onedrive_recordings');
GO
