<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PUNKTET BBS Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasjon for PUNKTET BBS-systemet
    | Alle verdier kan overstyres i .env
    |
    */

    // ===================
    // BBS Info
    // ===================
    
    'name' => env('BBS_NAME', 'PUNKTET'),
    'sysop' => env('BBS_SYSOP', 'SysOp'),
    'sysop_email' => env('BBS_SYSOP_EMAIL', 'sysop@punktet.no'),
    'location' => env('BBS_LOCATION', 'Norge'),
    'established' => env('BBS_ESTABLISHED', '2025'),
    'version' => '1.0.0',
    'tagline' => env('BBS_TAGLINE', 'Nostalgi møter fremtiden'),

    // ===================
    // Noder
    // ===================

    'nodes' => [
        'total' => env('BBS_NODES', 10),
        'timeout' => env('BBS_NODE_TIMEOUT', 300), // Sekunder før node frigjøres
        'max_idle' => env('BBS_MAX_IDLE', 900), // Maks inaktiv tid
    ],

    // ===================
    // Brukernivåer
    // ===================

    'levels' => [
        'GUEST' => 0,
        'NEW' => 10,
        'MEMBER' => 20,
        'VERIFIED' => 30,
        'ELITE' => 50,
        'COSYSOP' => 90,
        'SYSOP' => 100,
    ],

    // ===================
    // Tidsbegrensninger (minutter per dag)
    // ===================

    'time_limits' => [
        'GUEST' => 15,
        'NEW' => 30,
        'MEMBER' => 60,
        'VERIFIED' => 90,
        'ELITE' => 180,
        'COSYSOP' => 1440,
        'SYSOP' => 0, // 0 = ubegrenset
    ],

    // ===================
    // Filer
    // ===================

    'files' => [
        'max_upload_size' => env('BBS_MAX_UPLOAD_SIZE', 104857600), // 100 MB
        'allowed_extensions' => [
            'zip', 'rar', '7z', 'gz', 'tar', 'bz2', 'xz', 'lha', 'lzh', 'arj',
            'txt', 'doc', 'docx', 'pdf', 'rtf', 'odt', 'nfo', 'diz',
            'gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico', 'webp',
            'mp3', 'wav', 'ogg', 'flac', 'mod', 'xm', 's3m', 'it', 'sid',
            'mp4', 'avi', 'mkv', 'webm',
            'ans', 'asc',
        ],
        'storage_path' => env('BBS_FILES_PATH', 'files'),
        
        // Ratio system
        'ratio_required' => env('BBS_RATIO_REQUIRED', true),
        'default_ratio' => env('BBS_DEFAULT_RATIO', 3), // 3:1 download/upload required
        'ratio_exempt_levels' => ['ELITE', 'COSYSOP', 'SYSOP'], // Users exempt from ratio
        
        // Credits system
        'credits_per_upload_mb' => env('BBS_CREDITS_PER_UPLOAD_MB', 10),
        'credits_per_download_mb' => env('BBS_CREDITS_PER_DOWNLOAD_MB', 5),
        'free_leech_enabled' => env('BBS_FREE_LEECH', false),
        
        // Virus scanning
        'virus_scan_enabled' => env('BBS_VIRUS_SCAN', false),
        'clamav_socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'clamscan_path' => env('CLAMSCAN_PATH', '/usr/bin/clamscan'),
        'quarantine_path' => env('BBS_QUARANTINE_PATH', 'quarantine'),
    ],

    // ===================
    // Meldinger
    // ===================

    'messages' => [
        'max_length' => env('BBS_MAX_MESSAGE_LENGTH', 64000),
        'max_subject' => 80,
        'quote_prefix' => '> ',
        'edit_time_limit' => env('BBS_EDIT_TIME_LIMIT', 3600), // 1 time
        'flood_protection' => env('BBS_FLOOD_PROTECTION', 30), // Sekunder mellom meldinger
    ],

    // ===================
    // Private meldinger
    // ===================

    'pm' => [
        'max_length' => env('BBS_MAX_PM_LENGTH', 16000),
        'max_inbox' => env('BBS_MAX_INBOX', 100),
        'retention_days' => env('BBS_PM_RETENTION_DAYS', 90),
    ],

    // ===================
    // Stories
    // ===================

    'stories' => [
        'max_length' => env('BBS_MAX_STORY_LENGTH', 128000),
        'min_rating' => 1,
        'max_rating' => 5,
        'featured_min_rating' => 4.0,
    ],

    // ===================
    // Spill (Doors)
    // ===================

    'games' => [
        'enabled' => env('BBS_GAMES_ENABLED', true),
        'reset_day' => 1, // Dag i måneden for reset av high scores
        'timeout' => env('BBS_GAME_TIMEOUT', 300),
        'max_plays_per_day' => [
            'GUEST' => 0,
            'NEW' => 1,
            'MEMBER' => 3,
            'VERIFIED' => 5,
            'ELITE' => 10,
            'COSYSOP' => 999,
            'SYSOP' => 999,
        ],
    ],

    // ===================
    // ANSI Art
    // ===================

    'ansi' => [
        'max_width' => 80,
        'max_height' => 25,
        'max_file_size' => env('BBS_ANSI_MAX_SIZE', 65536), // 64 KB
        'sauce_enabled' => true,
    ],

    // ===================
    // Oneliners
    // ===================

    'oneliners' => [
        'max_length' => 79,
        'display_count' => env('BBS_ONELINER_COUNT', 10),
        'cooldown' => env('BBS_ONELINER_COOLDOWN', 60), // Sekunder mellom oneliners
    ],

    // ===================
    // Rate Limiting
    // ===================

    'rate_limits' => [
        'GUEST' => 30,      // Requests per minutt
        'NEW' => 45,
        'MEMBER' => 60,
        'VERIFIED' => 80,
        'ELITE' => 100,
        'COSYSOP' => 150,
        'SYSOP' => 200,
    ],

    // ===================
    // Sikkerhet
    // ===================

    'security' => [
        'max_login_attempts' => env('BBS_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('BBS_LOCKOUT_DURATION', 900), // 15 minutter
        'password_min_length' => 8,
        'require_email_verification' => env('BBS_REQUIRE_EMAIL_VERIFY', true),
        'log_all_actions' => env('BBS_LOG_ALL_ACTIONS', false),
        'ip_tracking' => env('BBS_IP_TRACKING', true),
        'session_lifetime' => env('BBS_SESSION_LIFETIME', 120), // Minutter
    ],

    // ===================
    // Vedlikehold
    // ===================

    'maintenance' => [
        'prune_sessions_older_than' => 7, // Dager
        'prune_logs_older_than' => 90, // Dager
        'prune_deleted_messages' => 30, // Dager
        'backup_enabled' => env('BBS_BACKUP_ENABLED', true),
        'backup_keep_days' => env('BBS_BACKUP_KEEP_DAYS', 7),
    ],

    // ===================
    // Cache
    // ===================

    'cache' => [
        'enabled' => env('BBS_CACHE_ENABLED', true),
        'ttl' => [
            'short' => 300,     // 5 minutter
            'medium' => 1800,   // 30 minutter
            'long' => 3600,     // 1 time
            'day' => 86400,     // 24 timer
        ],
    ],

    // ===================
    // Logging
    // ===================

    'logging' => [
        'caller_log' => env('BBS_CALLER_LOG', true),
        'activity_log' => env('BBS_ACTIVITY_LOG', true),
        'error_log' => env('BBS_ERROR_LOG', true),
        'debug_mode' => env('BBS_DEBUG_MODE', false),
    ],

    // ===================
    // Display
    // ===================

    'display' => [
        'default_rows' => 24,
        'default_cols' => 80,
        'color_enabled' => true,
        'ansi_enabled' => true,
        'utf8_enabled' => true,
    ],

    // ===================
    // Internasjonalisering
    // ===================

    'i18n' => [
        'default_locale' => env('BBS_DEFAULT_LOCALE', 'no'),
        'supported_locales' => ['no', 'en'],
        'fallback_locale' => 'en',
    ],

    // ===================
    // API
    // ===================

    'api' => [
        'version' => 'v1',
        'rate_limit' => env('BBS_API_RATE_LIMIT', 60),
        'throttle_exceptions' => [
            'api/health/ping',
            'api/health/status',
        ],
    ],

    // ===================
    // Klubber
    // ===================

    'clubs' => [
        'max_per_user' => env('BBS_MAX_CLUBS_PER_USER', 3),
        'min_level_to_create' => 'VERIFIED',
        'max_members' => env('BBS_MAX_CLUB_MEMBERS', 100),
    ],

    // ===================
    // Tidsbank
    // ===================

    'time_bank' => [
        'enabled' => env('BBS_TIME_BANK_ENABLED', true),
        'max_balance' => env('BBS_TIME_BANK_MAX', 480), // Minutter
        'max_deposit' => 60, // Maks per gang
        'max_withdraw' => 30,
    ],

    // ===================
    // Avstemninger
    // ===================

    'polls' => [
        'max_options' => env('BBS_POLL_MAX_OPTIONS', 10),
        'max_duration_days' => env('BBS_POLL_MAX_DAYS', 30),
        'min_level_to_create' => 'MEMBER',
    ],

    // ===================
    // Graffiti Wall
    // ===================

    'graffiti' => [
        'max_length' => 160,
        'display_count' => 20,
        'cooldown' => 300, // 5 minutter
    ],
];
