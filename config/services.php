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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    // Catálogo de webinars de Berstic Live (títulos y fechas del módulo Emociones).
    // El token va SOLO en el .env (BERSTICLIVE_API_TOKEN) y se envía como «Authorization: Bearer».
    'bersticlive' => [
        'url' => env('BERSTICLIVE_API_URL', 'https://bersticlive.org/api/meetings'),
        'token' => env('BERSTICLIVE_API_TOKEN'),
        'timeout' => (int) env('BERSTICLIVE_API_TIMEOUT', 15),
    ],

    'firebase' => [
        'credentials' => [
            'file' => base_path(env('FIREBASE_CREDENTIALS')),
        ],
        'database' => [
            'url' => env('FIREBASE_DATABASE_URL'),
        ],
    ],
];
