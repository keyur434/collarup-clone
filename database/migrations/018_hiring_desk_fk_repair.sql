-- If 017 failed mid-run, re-run migrate after 017 fix. This repairs FKs on partially created tables.
IF OBJECT_ID(N'cv_summaries', N'U') IS NOT NULL
BEGIN
    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_cvs_application')
        ALTER TABLE cv_summaries DROP CONSTRAINT FK_cvs_application;
    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_cvs_candidate')
        ALTER TABLE cv_summaries DROP CONSTRAINT FK_cvs_candidate;
    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_cvs_candidate')
        ALTER TABLE cv_summaries ADD CONSTRAINT FK_cvs_candidate
            FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE NO ACTION;
    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_cvs_application')
        ALTER TABLE cv_summaries ADD CONSTRAINT FK_cvs_application
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE NO ACTION;
END
GO

IF OBJECT_ID(N'hiring_desk_decision_notes', N'U') IS NOT NULL
BEGIN
    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_hddn_app')
        ALTER TABLE hiring_desk_decision_notes DROP CONSTRAINT FK_hddn_app;
    IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_hddn_app')
        ALTER TABLE hiring_desk_decision_notes ADD CONSTRAINT FK_hddn_app
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE NO ACTION;
    IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_hddn_user')
       AND NOT EXISTS (
           SELECT 1 FROM sys.foreign_keys fk
           JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
           WHERE fk.name = N'FK_hddn_user' AND fk.delete_referential_action = 0
       )
    BEGIN
        ALTER TABLE hiring_desk_decision_notes DROP CONSTRAINT FK_hddn_user;
        ALTER TABLE hiring_desk_decision_notes ADD CONSTRAINT FK_hddn_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE NO ACTION;
    END
END
GO
