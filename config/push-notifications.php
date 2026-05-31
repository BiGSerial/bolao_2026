<?php

return [
    'enabled' => (bool) env('PUSH_NOTIFICATIONS_ENABLED', false),
    'pool_join_request_enabled' => (bool) env('PUSH_POOL_JOIN_REQUEST_ENABLED', true),
    'daily_ranking_enabled' => (bool) env('PUSH_DAILY_RANKING_ENABLED', true),
    'match_score_enabled' => (bool) env('PUSH_MATCH_SCORE_ENABLED', true),
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', ''),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],
];

