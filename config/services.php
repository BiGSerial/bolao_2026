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

    'kinghost_smtp' => [
        'base_url' => env('KINGHOST_SMTP_API_URL', 'https://api.smtplw.com.br/v1'),
        'token' => env('KINGHOST_SMTP_API_TOKEN'),
        'from_address' => env('KINGHOST_SMTP_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('KINGHOST_SMTP_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME'))),
        'timeout' => (int) env('KINGHOST_SMTP_TIMEOUT', 15),
        'retry_times' => (int) env('KINGHOST_SMTP_RETRY_TIMES', 3),
        'retry_sleep' => (int) env('KINGHOST_SMTP_RETRY_SLEEP', 500),
        'monthly_limit' => (int) env('KINGHOST_SMTP_MONTHLY_LIMIT', 0),
    ],

];
