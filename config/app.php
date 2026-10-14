<?php



return [

    'name' => getenv('APP_NAME') ?: 'TalentPulse',

    'url' => getenv('APP_URL') ?: 'http://localhost/collarup-clone/public',

    'env' => getenv('APP_ENV') ?: 'local',

    'debug' => getenv('APP_DEBUG') === 'true',

    'timezone' => 'Asia/Kolkata',

    // HM / interviewer picker: directory cached in DB, refreshed when older than N days
    'employee_directory_cache_ttl_days' => (int) (getenv('EMPLOYEE_DIRECTORY_CACHE_TTL_DAYS') ?: 7),

    'session_name' => 'collarup_session',

    'upload_max_mb' => 500,

    'resume_max_mb' => 10,

    'allowed_resume_types' => ['pdf', 'doc', 'docx'],

    'allowed_video_types' => ['mp4', 'webm', 'mov', 'mkv'],

    'allow_local_login' => getenv('AUTH_ALLOW_LOCAL_LOGIN') === 'true',

    // Shared report links: require portal sign-in (Microsoft SSO when configured) + job stakeholder
    'report_share_require_login' => getenv('REPORT_SHARE_REQUIRE_LOGIN') !== 'false',

    // Phase 7 proctoring — set SHOW_INTEGRITY_UI=true when ready
    'show_integrity_ui' => getenv('SHOW_INTEGRITY_UI') === 'true',

    // Auto-create Teams meeting when HR saves schedule
    'teams_auto_create_meetings' => getenv('TEAMS_AUTO_CREATE_MEETINGS') !== 'false',

    // true = all portal schedules create Teams meetings as TEAMS_ORGANIZER_USER_ID (e.g. hr@) — recruiters never need that mailbox
    'teams_shared_organizer' => getenv('TEAMS_SHARED_ORGANIZER') === 'true',

    // Manual video upload / paste link (off by default for HR simplicity)
    'show_manual_video_upload' => getenv('SHOW_MANUAL_VIDEO_UPLOAD') === 'true',

    // Hiring desk preview (separate routes/views; does not alter leaderboard, compare, or job UIs)
    'hiring_desk_beta' => getenv('HIRING_DESK_BETA') === 'true',

    // Keka schedules in Teams; recruiters paste join link in TalentPulse (no Keka Hire API)
    'teams_keka_register_mode' => getenv('TEAMS_KEKA_REGISTER_MODE') !== 'false',

    // Minutes after scheduled slot end before first Graph poll (also polls earlier — see TeamsRecordingService)
    'teams_recording_fetch_buffer_minutes' => (int) (getenv('TEAMS_RECORDING_FETCH_BUFFER_MINUTES') ?: 5),

    'score_labels' => [

        ['min' => 8, 'label' => 'Good', 'class' => 'score-good'],

        ['min' => 6, 'label' => 'Average', 'class' => 'score-average'],

        ['min' => 0, 'label' => 'Poor', 'class' => 'score-poor'],

    ],

];


