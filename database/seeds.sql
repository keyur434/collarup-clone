USE collarup_clone;
GO

-- Departments
IF NOT EXISTS (SELECT 1 FROM departments)
INSERT INTO departments (name) VALUES
(N'Operations'), (N'Sales & Marketing'), (N'Human Resource'), (N'Information Technology'),
(N'Finance & Accounts'), (N'Finance & Accounts - Credit Control'), (N'Projects'), (N'Intern'),
(N'Sales (Lime Division)');
GO

IF NOT EXISTS (SELECT 1 FROM seniority_levels)
INSERT INTO seniority_levels (name, sort_order) VALUES
(N'Executive', 1), (N'Mid Senior', 2), (N'Manager', 3), (N'Senior Manager', 4), (N'AVP', 5);
GO

IF NOT EXISTS (SELECT 1 FROM countries)
INSERT INTO countries (name, code) VALUES (N'India', N'IN'), (N'United States', N'US');
GO

IF NOT EXISTS (SELECT 1 FROM states)
INSERT INTO states (country_id, name) VALUES
(1, N'Gujarat'), (1, N'Tamil Nadu'), (1, N'Haryana'), (1, N'Maharashtra');
GO

IF NOT EXISTS (SELECT 1 FROM cities)
INSERT INTO cities (state_id, name) VALUES
(1, N'Surat'), (2, N'Hosur'), (3, N'Jhajjar'), (4, N'Mumbai');
GO

IF NOT EXISTS (SELECT 1 FROM work_modes)
INSERT INTO work_modes (name) VALUES (N'Onsite'), (N'Remote'), (N'Hybrid');
GO

IF NOT EXISTS (SELECT 1 FROM candidate_sources)
INSERT INTO candidate_sources (name) VALUES
(N'Employee Referral'), (N'Manager Referral'), (N'LinkedIn'), (N'Career Portal'),
(N'Consultant'), (N'Testing');
GO

IF NOT EXISTS (SELECT 1 FROM experience_bands)
INSERT INTO experience_bands (name, min_years, max_years, sort_order) VALUES
(N'Less Than 1 Year', 0, 0.9, 1),
(N'1 To 2 Years', 1, 2, 2),
(N'3 To 5 Years', 3, 5, 3),
(N'6 To 10 Years', 6, 10, 4),
(N'10+ Years', 10, NULL, 5);
GO

-- Default owner (password set by install.php)
IF NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@company.local')
INSERT INTO users (first_name, last_name, email, password_hash, role, initials) VALUES
(N'Admin', N'User', N'admin@company.local', N'$2y$10$placeholder', N'owner', N'AU');
GO
