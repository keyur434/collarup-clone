USE collarup_clone;
GO

IF OBJECT_ID(N'skill_tags', N'U') IS NULL
BEGIN
    CREATE TABLE skill_tags (
        id INT IDENTITY(1,1) PRIMARY KEY,
        name NVARCHAR(100) NOT NULL,
        is_active BIT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        CONSTRAINT UQ_skill_tags_name UNIQUE (name)
    );
END
GO

INSERT INTO skill_tags (name, sort_order)
SELECT v.name, v.sort_order FROM (VALUES
(N'Communication', 1), (N'Leadership', 2), (N'Problem Solving', 3), (N'Critical Thinking', 4),
(N'Team Management', 5), (N'Project Management', 6), (N'Stakeholder Management', 7),
(N'Negotiation', 8), (N'Presentation Skills', 9), (N'Business Development', 10),
(N'Sales', 11), (N'Marketing', 12), (N'Digital Marketing', 13), (N'Brand Management', 14),
(N'Customer Service', 15), (N'Account Management', 16), (N'JavaScript', 20), (N'TypeScript', 21),
(N'React', 22), (N'Node.js', 23), (N'PHP', 24), (N'Laravel', 25), (N'Python', 26),
(N'Django', 27), (N'Java', 28), (N'Spring Boot', 29), (N'C#', 30), (N'.NET', 31),
(N'SQL', 32), (N'MySQL', 33), (N'PostgreSQL', 34), (N'MSSQL', 35), (N'MongoDB', 36),
(N'Redis', 37), (N'AWS', 40), (N'Azure', 41), (N'Docker', 42), (N'Kubernetes', 43),
(N'CI/CD', 44), (N'Git', 45), (N'Rest API', 46), (N'Microservices', 47),
(N'Data Analysis', 50), (N'Power BI', 51), (N'Tableau', 52), (N'Microsoft Excel', 53),
(N'SAP', 54), (N'ERP', 55), (N'Finance', 56), (N'Budgeting', 57), (N'Cost Control', 58),
(N'Procurement', 59), (N'Supply Chain', 60), (N'Logistics', 61), (N'Inventory Management', 62),
(N'Production Planning', 63), (N'Quality Assurance', 64), (N'Six Sigma', 65), (N'Lean Manufacturing', 66),
(N'Safety Compliance', 67), (N'ISO Standards', 68), (N'Autoclaved Aerated Concrete', 69),
(N'Construction Materials', 70), (N'Civil Engineering', 71), (N'Structural Design', 72),
(N'AutoCAD', 73), (N'SolidWorks', 74), (N'HSE', 75), (N'Plant Operations', 76),
(N'Maintenance', 77), (N'Electrical Engineering', 78), (N'Mechanical Engineering', 79),
(N'HR Operations', 80), (N'Talent Acquisition', 81), (N'Payroll', 82), (N'Labour Laws', 83),
(N'English', 90), (N'Hindi', 91), (N'Gujarati', 92), (N'MS Office', 93), (N'Google Workspace', 94)
) AS v(name, sort_order)
WHERE NOT EXISTS (SELECT 1 FROM skill_tags s WHERE s.name = v.name);
GO
