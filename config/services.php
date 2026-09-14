<?php



return [

    'azure_ad_login' => [

        'tenant_id' => getenv('AZURE_AD_LOGIN_TENANT_ID') ?: getenv('AZURE_AD_TENANT_ID') ?: '',

        'client_id' => getenv('AZURE_AD_LOGIN_CLIENT_ID') ?: '',

        'client_secret' => getenv('AZURE_AD_LOGIN_CLIENT_SECRET') ?: '',

        'redirect_uri' => getenv('AZURE_AD_LOGIN_REDIRECT_URI') ?: '',

    ],

    'azure_ad_sync' => [

        'tenant_id' => getenv('AZURE_AD_SYNC_TENANT_ID') ?: getenv('AZURE_AD_TENANT_ID') ?: '',

        'client_id' => getenv('AZURE_AD_SYNC_CLIENT_ID') ?: '',

        'client_secret' => getenv('AZURE_AD_SYNC_CLIENT_SECRET') ?: '',

    ],

    'onedrive' => [

        'drive_id' => getenv('ONEDRIVE_DRIVE_ID') ?: '',

        'folder_path' => getenv('ONEDRIVE_FOLDER_PATH') ?: '/InterviewRecordings',

        'teams_organizer_user_id' => getenv('TEAMS_ORGANIZER_USER_ID') ?: '',

    ],

    'azure_openai' => [

        'endpoint' => getenv('AZURE_OPENAI_ENDPOINT') ?: '',

        'endpoint_fallbacks' => array_values(array_filter(array_map('trim', explode(',', getenv('AZURE_OPENAI_ENDPOINT_FALLBACKS') ?: '')))),

        'api_key' => getenv('AZURE_OPENAI_API_KEY') ?: '',

        'deployment' => getenv('AZURE_OPENAI_DEPLOYMENT') ?: 'gpt-4.1',

        'deployment_id' => getenv('AZURE_OPENAI_DEPLOYMENT_ID') ?: '',

        'extraction_deployment' => getenv('AZURE_OPENAI_EXTRACTION_DEPLOYMENT') ?: 'gpt-4.1-mini',

        'api_version' => getenv('AZURE_OPENAI_API_VERSION') ?: '2025-01-01-preview',

        'api_mode' => getenv('AZURE_OPENAI_API_MODE') ?: 'auto',

        'use_bearer' => getenv('AZURE_OPENAI_USE_BEARER') !== 'false',

        'refine_deployment' => getenv('AZURE_OPENAI_REFINE_DEPLOYMENT') ?: 'gpt-4.1-mini',

        'refine_enabled' => getenv('REFINE_TRANSCRIPT') !== 'false',

        'analysis_pipeline' => getenv('ANALYSIS_PIPELINE') ?: 'multipass',

    ],

    'elevenlabs' => [

        'api_key' => getenv('ELEVENLABS_API_KEY') ?: '',

        'model' => getenv('ELEVENLABS_STT_MODEL') ?: 'scribe_v2',

        'diarize' => getenv('ELEVENLABS_DIARIZE') !== 'false',

        'num_speakers' => (int) (getenv('ELEVENLABS_NUM_SPEAKERS') ?: 2),

        'detect_speaker_roles' => getenv('ELEVENLABS_DETECT_SPEAKER_ROLES') === 'true',

        'tag_audio_events' => getenv('ELEVENLABS_TAG_AUDIO_EVENTS') === 'true',

        'language_code' => getenv('ELEVENLABS_LANGUAGE_CODE') ?: '',

        'keyterms' => getenv('ELEVENLABS_KEYTERMS') ?: '',

    ],

    'ffmpeg' => [

        'binary' => getenv('FFMPEG_PATH') ?: 'ffmpeg',

    ],

    'smtp' => [

        'host' => getenv('SMTP_HOST') ?: '',

        'port' => getenv('SMTP_PORT') ?: 587,

        'username' => getenv('SMTP_USERNAME') ?: '',

        'password' => getenv('SMTP_PASSWORD') ?: '',

        'from_email' => getenv('SMTP_FROM') ?: 'noreply@company.local',

        'from_name' => getenv('SMTP_FROM_NAME') ?: 'CollarUp Clone',

    ],

    'keka' => [

        'tenant' => getenv('KEKA_TENANT') ?: '',

        'api_base' => getenv('KEKA_API_BASE') ?: '',

        'client_id' => getenv('KEKA_CLIENT_ID') ?: '',

        'client_secret' => getenv('KEKA_CLIENT_SECRET') ?: '',

        'api_key' => getenv('KEKA_API_KEY') ?: '',

    ],

];


