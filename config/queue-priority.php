<?php

return [
    'jobs' => [
        'scoring' => env('QUEUE_NAME_SCORING', 'scoring'),
        'ranking' => env('QUEUE_NAME_RANKING', 'ranking'),
    ],
    'broadcast' => [
        'events' => env('QUEUE_NAME_BROADCAST', 'broadcast'),
    ],
];

