-- Store raw ElevenLabs STT before GPT refinement pass
IF COL_LENGTH('transcripts', 'raw_full_text') IS NULL
    ALTER TABLE transcripts ADD raw_full_text NVARCHAR(MAX) NULL;
