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
    'sharing' => [
        'storage' => env('VIDEO_DS', 'video'),
        'share_interval' => env('SHARE_INTERVAL'),
        'reminder_interval' => env('REMINDER_INTERVAL'),
    ],
    'dropbox' => [
        'client_id' => env('DROPBOX_CLIENT_ID'),
        'client_secret' => env('DROPBOX_CLIENT_SECRET'),
        'scopes' => 'files.content.write files.content.read',
    ],
    'ffmpeg' => [
        'bin' => env('FFMPEG_BIN', '/usr/bin/ffmpeg'),
        'crf' => env('FFMPEG_CRF', 28),
        'preset' => env('FFMPEG_PRESET', 'veryfast'),
        'timeout' => env('FFMPEG_TIMEOUT', 3600),      // max. Laufzeit in Sekunden (1h)
        'idle_timeout' => env('FFMPEG_IDLE_TIMEOUT', null), // z. B. 60
    ],
];
