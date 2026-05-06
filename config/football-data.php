<?php

return [
    'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),
    'token' => env('FOOTBALL_DATA_TOKEN'),
    'world_cup' => [
        'id' => 2000,
        'code' => 'WC',
        'season' => 2026,
        'stage' => 'GROUP_STAGE',
    ],
    'rate_limit' => [
        'free_requests_per_minute' => 10,
    ],
];
