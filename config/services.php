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

    /*
     * Google integrations (F15 Drive backup + F16 Contacts sync).
     * Consumed by App\Services\GoogleDriveUploader / GoogleContactsSync.
     * Values stay empty until the live OAuth grant — see docs/RUNBOOK_GOOGLE.md.
     */
    'google' => [
        // Path to the OAuth 2.0 client credentials JSON downloaded from Google
        // Cloud (RUNBOOK_GOOGLE step 2).
        'oauth_client_json' => env('GOOGLE_OAUTH_CLIENT_JSON'),
        'drive_folder_name' => env('GOOGLE_DRIVE_FOLDER_NAME', 'FA Distribuidora — Backups'),
        'contacts_group' => env('GOOGLE_CONTACTS_GROUP', 'FA Distribuidora'),
    ],

];
