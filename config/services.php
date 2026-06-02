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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_speech' => [
        'key' => env('GOOGLE_SPEECH_API_KEY'),
        'driver' => env('SPEECH_DRIVER', 'google'),

        // Switch between the v1 REST API (API key) and v2 Chirp (service account).
        'version' => env('GOOGLE_SPEECH_VERSION', 'v2'),

        // Used only when version = v2. Requires a service-account JSON key.
        'project_id'  => env('GOOGLE_CLOUD_PROJECT_ID'),
        'location'    => env('GOOGLE_SPEECH_LOCATION', 'us-central1'),
        'model'       => env('GOOGLE_SPEECH_MODEL', 'chirp_2'),
        // Relative paths are resolved from the project root so the same .env
        // works locally and on the server.
        'credentials' => ($cred = env('GOOGLE_APPLICATION_CREDENTIALS'))
            ? (str_starts_with($cred, '/') ? $cred : base_path($cred))
            : null,
    ],

];
