USE collarup_clone;
GO

IF OBJECT_ID(N'employee_directory_cache', N'U') IS NULL
CREATE TABLE employee_directory_cache (
    picker_value NVARCHAR(200) NOT NULL PRIMARY KEY,
    label NVARCHAR(500) NOT NULL,
    email NVARCHAR(255) NULL,
    search_text NVARCHAR(700) NOT NULL,
    source NVARCHAR(20) NOT NULL,
    refreshed_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes WHERE name = N'IX_employee_directory_cache_search' AND object_id = OBJECT_ID(N'employee_directory_cache')
)
CREATE INDEX IX_employee_directory_cache_search ON employee_directory_cache (search_text);
GO
