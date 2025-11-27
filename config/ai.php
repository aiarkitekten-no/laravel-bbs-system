<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Node Simulation
    |--------------------------------------------------------------------------
    */
    'nodes' => [
        'enabled' => env('AI_NODES_ENABLED', true),
        'count' => env('AI_NODES_COUNT', 2),
        'activity_interval' => env('AI_ACTIVITY_INTERVAL', 30),
        'min_session_time' => env('AI_MIN_SESSION_TIME', 300),
        'max_session_time' => env('AI_MAX_SESSION_TIME', 1800),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Personalities
    |--------------------------------------------------------------------------
    */
    'personalities' => [
        'usernames' => explode(',', env('AI_USERNAMES', 'RetroBot,SysBot,NordicAI,BBSHelper')),
        'locations' => explode(',', env('AI_LOCATIONS', 'Silicon Valley,Oslo Norway,Cyberspace,The Cloud,Retro Land')),
        'activities' => explode(',', env('AI_ACTIVITIES', 'Reading messages,Browsing files,Playing games,Writing story,Checking polls,Viewing ANSI art,Chatting')),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Content Generation
    |--------------------------------------------------------------------------
    */
    'stories' => [
        'enabled' => env('AI_STORIES_ENABLED', true),
        'interval_hours' => env('AI_STORY_INTERVAL', 24),
        'categories' => explode(',', env('AI_STORY_CATEGORIES', 'it-kaos,juss-byrakrati,helse')),
        'max_length' => env('AI_STORY_MAX_LENGTH', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Chat/Interaction
    |--------------------------------------------------------------------------
    */
    'oneliners' => [
        'enabled' => env('AI_ONELINERS_ENABLED', true),
        'interval_minutes' => env('AI_ONELINER_INTERVAL', 60),
    ],

    'auto_reply' => [
        'enabled' => env('AI_AUTO_REPLY_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Integration
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 500),
        'temperature' => env('OPENAI_TEMPERATURE', 0.8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_actions_per_hour' => env('AI_MAX_ACTIONS_PER_HOUR', 60),
        'action_cooldown' => env('AI_ACTION_COOLDOWN', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'file' => env('AI_LOG_FILE', 'ai-activity.log'),
        'level' => env('AI_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in AI Responses (when OpenAI not configured)
    |--------------------------------------------------------------------------
    */
    'builtin_oneliners' => [
        'Anyone remember when 56k modems were fast? Good times!',
        'Just found some awesome ANSI art in the gallery!',
        'This BBS brings back so many memories...',
        'Greetings from the digital realm!',
        'Nothing beats the sound of a modem connecting.',
        'Who else is playing door games today?',
        'The retro vibes here are amazing!',
        'Remember: Always be excellent to each other!',
        'BBS life is the best life.',
        'Anyone want to trade some files?',
        'Just posted a new story, check it out!',
        'The sysop here is pretty cool.',
        'Old school is the best school.',
        'Downloading at 9600 baud... the nostalgia!',
        'ANSI art is an underappreciated art form.',
        // Norske oneliners
        'Hei på deg! Kos deg på PUNKTET!',
        'Noen som husker Stranda BBS?',
        'God stemning her i dag!',
        'Retro-tech er best-tech!',
        'Norge trenger flere BBS-er!',
        'Savner de gode gamle dagene med FidoNet.',
        'Har noen lest dagens historie?',
        'Sysop gjør en fantastisk jobb her!',
        'BBS-kulturen lever!',
        'Noen andre som er online nå?',
        'Kult med et norsk BBS!',
        'Stemte akkurat på en poll - sjekk den ut!',
        'ANSI-kunst er undervurdert!',
        'Hvem spiller door games?',
        'Nostalgi-alarm! *modem-lyder*',
    ],

    'builtin_activities' => [
        'Reading messages',
        'Browsing file areas',
        'Playing TradeWars',
        'Checking new uploads',
        'Writing a message',
        'Viewing ANSI gallery',
        'Reading today\'s story',
        'Voting in polls',
        'Checking oneliners',
        'Exploring the BBS',
    ],
];
