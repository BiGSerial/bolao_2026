<?php

return [
    'monthly_quota' => (int) env('EMAIL_MONTHLY_QUOTA', 10000),
    'limits' => [
        'pool_invite_sender_hour' => (int) env('EMAIL_LIMIT_POOL_INVITE_SENDER_HOUR', 20),
        'pool_invite_recipient_day' => (int) env('EMAIL_LIMIT_POOL_INVITE_RECIPIENT_DAY', 3),
        'password_reset_email_hour' => (int) env('EMAIL_LIMIT_PASSWORD_RESET_EMAIL_HOUR', 3),
        'email_verification_user_hour' => (int) env('EMAIL_LIMIT_EMAIL_VERIFICATION_USER_HOUR', 3),
        'temporary_password_user_day' => (int) env('EMAIL_LIMIT_TEMP_PASSWORD_USER_DAY', 2),
    ],
];

