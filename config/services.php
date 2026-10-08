<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
     */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'vision_credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'books_api_key' => env('GOOGLE_BOOKS_API_KEY'),
    ],

    // Lokální LLM (Ollama API). Na VPS přes VPN http://10.7.0.2:11434,
    // v Sail kontejneru http://host.docker.internal:11434.
    'llm' => [
        'base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:11434'),
        'model' => env('LLM_MODEL', 'qwen3-coder:30b'),
        'vision_model' => env('LLM_VISION_MODEL', 'qwen2.5vl:7b'),
        'timeout' => (int) env('LLM_TIMEOUT', 300),
        'keep_alive' => env('LLM_KEEP_ALIVE', '10m'),
    ],

    // Čtení tiráže: 'google' = Google Vision OCR + regex, 'llm' = vision LLM vrací rovnou strukturovaná data
    'book_scan' => [
        'driver' => env('BOOK_SCAN_DRIVER', 'google'),
        'llm_image_size' => (int) env('BOOK_SCAN_LLM_IMAGE_SIZE', 1280),
    ],

];
