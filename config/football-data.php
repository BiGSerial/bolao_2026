<?php

$defaultCompetitionCode = env('FOOTBALL_DATA_DEFAULT_COMPETITION_CODE', 'WC');

return [
    'base_url' => env('FOOTBALL_DATA_BASE_URL', 'https://api.football-data.org/v4'),
    'token' => env('FOOTBALL_DATA_TOKEN'),

    'default_competition_code' => $defaultCompetitionCode,

    'competitions' => [
        'WC' => [
            'id'            => 2000,
            'code'          => 'WC',
            'name'          => 'Copa do Mundo',
            'type'          => 'cup',     // cup | league
            'season'        => (int) env('FOOTBALL_DATA_WC_SEASON', 2026),
            'default_stage' => env('FOOTBALL_DATA_WC_STAGE', 'GROUP_STAGE'),
            'enabled'       => (bool) env('FOOTBALL_DATA_WC_ENABLED', true),
        ],
        'BSA' => [
            'id'            => 2013,
            'code'          => 'BSA',
            'name'          => 'Brasileirão Série A',
            'type'          => 'league',  // cup | league
            'season'        => (int) env('FOOTBALL_DATA_BSA_SEASON', 2026),
            'default_stage' => env('FOOTBALL_DATA_BSA_STAGE', 'REGULAR_SEASON'),
            'enabled'       => (bool) env('FOOTBALL_DATA_BSA_ENABLED', false),
        ],
    ],

    // Retrocompatibilidade com código legado da Copa
    'world_cup' => [
        'id' => 2000,
        'code' => 'WC',
        'season' => (int) env('FOOTBALL_DATA_WC_SEASON', 2026),
        'stage' => env('FOOTBALL_DATA_WC_STAGE', 'GROUP_STAGE'),
    ],

    'rate_limit' => [
        'free_requests_per_minute' => 10,
        'lock_key' => env('FOOTBALL_DATA_RATE_LOCK_KEY', 'football-data:rate-limit:lock'),
        'counter_key_prefix' => env('FOOTBALL_DATA_RATE_COUNTER_PREFIX', 'football-data:rate-limit:counter:'),
        'max_wait_seconds' => (int) env('FOOTBALL_DATA_RATE_MAX_WAIT_SECONDS', 120),
    ],

    'response_cache' => [
        'enabled' => (bool) env('FOOTBALL_DATA_RESPONSE_CACHE_ENABLED', true),
        'key_prefix' => env('FOOTBALL_DATA_RESPONSE_CACHE_PREFIX', 'football-data:response:'),
        'default_ttl_seconds' => (int) env('FOOTBALL_DATA_RESPONSE_CACHE_TTL', 30),
        // Regex => TTL em segundos
        'ttl_by_endpoint' => [
            '#^/matches/\d+$#' => (int) env('FOOTBALL_DATA_RESPONSE_CACHE_TTL_MATCH_DETAIL', 120),
            '#^/competitions/[^/]+/matches$#' => (int) env('FOOTBALL_DATA_RESPONSE_CACHE_TTL_COMPETITION_MATCHES', 60),
            '#^/competitions/[^/]+/standings$#' => (int) env('FOOTBALL_DATA_RESPONSE_CACHE_TTL_STANDINGS', 300),
            '#^/competitions/[^/]+/teams$#' => (int) env('FOOTBALL_DATA_RESPONSE_CACHE_TTL_TEAMS', 3600),
            '#^/competitions/[^/]+$#' => (int) env('FOOTBALL_DATA_RESPONSE_CACHE_TTL_COMPETITION', 1800),
        ],
    ],

    'match_details' => [
        'sync_limit_per_minute' => 8,
        'stale_minutes' => 15,
        'backfill_finished_days' => (int) env('FOOTBALL_DATA_DETAILS_BACKFILL_DAYS', 120),
    ],
];
