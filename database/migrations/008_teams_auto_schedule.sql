-- Auto Teams meetings per interview (organizer = recruiter who schedules)
IF COL_LENGTH('interviews', 'teams_meeting_id') IS NULL
    ALTER TABLE interviews ADD teams_meeting_id NVARCHAR(200) NULL;
GO
IF COL_LENGTH('interviews', 'teams_organizer_user_id') IS NULL
    ALTER TABLE interviews ADD teams_organizer_user_id NVARCHAR(64) NULL;
GO
IF COL_LENGTH('interviews', 'recording_fetch_state') IS NULL
    ALTER TABLE interviews ADD recording_fetch_state NVARCHAR(30) NULL;
GO
IF COL_LENGTH('interviews', 'recording_fetch_after') IS NULL
    ALTER TABLE interviews ADD recording_fetch_after DATETIME2 NULL;
GO
IF COL_LENGTH('interviews', 'recording_fetch_attempts') IS NULL
    ALTER TABLE interviews ADD recording_fetch_attempts INT NOT NULL DEFAULT 0;
GO
