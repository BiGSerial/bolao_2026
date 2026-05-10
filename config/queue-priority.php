<?php

return [
    'sync' => [
        'api' => env('QUEUE_NAME_API_SYNC', 'api-sync'),
        'api_funnel' => env('QUEUE_NAME_API_FUNNEL', 'api-funnel'),
    ],
    'jobs' => [
        'scoring' => env('QUEUE_NAME_SCORING', 'scoring'),
        'ranking' => env('QUEUE_NAME_RANKING', 'ranking'),
    ],
    'mail' => [
        'default' => env('QUEUE_NAME_MAIL', 'mail'),
    ],
    'broadcast' => [
        'events' => env('QUEUE_NAME_BROADCAST', 'broadcast'),
    ],
];
