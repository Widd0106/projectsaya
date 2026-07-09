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


    'gemini' => [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        // Urutan model dari yang paling diutamakan -> cadangan.
        // Model pertama gagal/limit -> OpenRouter otomatis coba yang berikutnya.
        // Default: 100% model GRATIS (akhiran :free / openrouter/free), tidak butuh kartu kredit.
        'models' => array_filter(array_map('trim', explode(',', env(
            'OPENROUTER_MODELS',
            'z-ai/glm-4.5-air:free,openai/gpt-oss-120b:free,openrouter/free'
        )))),
    ],

];