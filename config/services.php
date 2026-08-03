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

    'fcm' => [
        'credentials_file' => env('FCM_CREDENTIALS_FILE', storage_path('app/firebase-credentials.json')),
        'project_id'       => env('FCM_PROJECT_ID', 'brijchat-6d93f'),
        'client_email'     => env('FCM_CLIENT_EMAIL', 'firebase-adminsdk-fbsvc@brijchat-6d93f.iam.gserviceaccount.com'),
        'private_key'      => env('FCM_PRIVATE_KEY'),
        'server_key'       => env('FCM_SERVER_KEY', env('FIREBASE_SERVER_KEY')),
        'vapid_key'        => env('FCM_VAPID_KEY'),
    ],

];
