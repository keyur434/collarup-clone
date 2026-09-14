<?php



return [

    'name' => 'CollarUp Clone',

    'url' => getenv('APP_URL') ?: 'https://order.magicrete.in/collarup-clone/public',

    'env' => getenv('APP_ENV') ?: 'local',

    'debug' => getenv('APP_DEBUG') === 'true',

    'timezone' => 'Asia/Kolkata',

    'session_name' => 'collarup_session',

    'upload_max_mb' => 500,

    'resume_max_mb' => 10,

    'allowed_resume_types' => ['pdf', 'doc', 'docx'],

    'allowed_video_types' => ['mp4', 'webm', 'mov', 'mkv'],

    'allow_local_login' => getenv('AUTH_ALLOW_LOCAL_LOGIN') === 'true',

    'score_labels' => [

        ['min' => 8, 'label' => 'Good', 'class' => 'score-good'],

        ['min' => 6, 'label' => 'Average', 'class' => 'score-average'],

        ['min' => 0, 'label' => 'Poor', 'class' => 'score-poor'],

    ],

];


