<?php



return [

    'name' => 'CollarUp Clone',

    'url' => getenv('APP_URL') ?: 'http://localhost/collarup-clone/public',

    'env' => getenv('APP_ENV') ?: 'local',

    'debug' => getenv('APP_DEBUG') === 'true',

    'timezone' => 'Asia/Kolkata',

    'session_name' => 'collarup_session',

    'upload_max_mb' => 500,

    'resume_max_mb' => 10,

    'allowed_resume_types' => ['pdf', 'doc', 'docx'],

    'allowed_video_types' => ['mp4', 'webm', 'mov', 'mkv'],

    'allow_local_login' => getenv('AUTH_ALLOW_LOCAL_LOGIN') === 'true',

    // Phase 7 proctoring — set SHOW_INTEGRITY_UI=true when ready
    'show_integrity_ui' => getenv('SHOW_INTEGRITY_UI') === 'true',

    // Auto-create Teams meeting when HR saves schedule
    'teams_auto_create_meetings' => getenv('TEAMS_AUTO_CREATE_MEETINGS') !== 'false',

    // true = all portal schedules create Teams meetings as TEAMS_ORGANIZER_USER_ID (e.g. hr@) — recruiters never need that mailbox
    'teams_shared_organizer' => getenv('TEAMS_SHARED_ORGANIZER') === 'true',

    // Manual video upload / paste link (off by default for HR simplicity)
    'show_manual_video_upload' => getenv('SHOW_MANUAL_VIDEO_UPLOAD') === 'true',

    // Keka schedules in Teams; recruiters paste join link in CollarUp (no Keka Hire API)
    'teams_keka_register_mode' => getenv('TEAMS_KEKA_REGISTER_MODE') !== 'false',

    'score_labels' => [

        ['min' => 8, 'label' => 'Good', 'class' => 'score-good'],

        ['min' => 6, 'label' => 'Average', 'class' => 'score-average'],

        ['min' => 0, 'label' => 'Poor', 'class' => 'score-poor'],

    ],

];


