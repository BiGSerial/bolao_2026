<?php

return [
    'enabled' => (bool) env('API_FOOTBALL_ENABLED', true),
    'base_url' => env('API_FOOTBALL_BASE_URL', 'https://v3.football.api-sports.io'),
    'token' => env('API_FOOTBALL_TOKEN'),

    'auth' => [
        'token_header' => 'x-apisports-key',
    ],

    'http' => [
        'timeout' => (int) env('API_FOOTBALL_TIMEOUT', 20),
        'retry' => [
            'times' => (int) env('API_FOOTBALL_RETRY_TIMES', 2),
            'sleep_ms' => (int) env('API_FOOTBALL_RETRY_SLEEP_MS', 1000),
        ],
    ],

    // Mapear o codigo da competicao interna para o league ID da API-FOOTBALL.
    'competitions' => [
        'WC' => [
            'league_id' => (int) env('API_FOOTBALL_WC_LEAGUE_ID', 1),
            'season' => (int) env('API_FOOTBALL_WC_SEASON', 2026),
        ],
        'BSA' => [
            'league_id' => (int) env('API_FOOTBALL_BSA_LEAGUE_ID', 71),
            'season' => (int) env('API_FOOTBALL_BSA_SEASON', 2026),
        ],
    ],

    'rate_limit' => [
        // Plano pago: 7500 req/hora = 125 req/min
        'requests_per_hour'        => (int) env('API_FOOTBALL_RATE_LIMIT_RPH', 7500),
        'requests_per_minute'      => (int) env('API_FOOTBALL_RATE_LIMIT_RPM', 125),
        'lock_key'                 => env('API_FOOTBALL_RATE_LOCK_KEY', 'api-football:rate-limit:lock'),
        'counter_key_prefix'       => env('API_FOOTBALL_RATE_COUNTER_PREFIX', 'api-football:rate-limit:counter:'),
        'max_wait_seconds'         => (int) env('API_FOOTBALL_RATE_MAX_WAIT_SECONDS', 30),
    ],

    'response_cache' => [
        'enabled' => (bool) env('API_FOOTBALL_RESPONSE_CACHE_ENABLED', true),
        'key_prefix' => env('API_FOOTBALL_RESPONSE_CACHE_PREFIX', 'api-football:response:'),
        'default_ttl_seconds' => (int) env('API_FOOTBALL_RESPONSE_CACHE_TTL', 30),
        'ttl_by_endpoint' => [
            '#^/fixtures$#' => (int) env('API_FOOTBALL_RESPONSE_CACHE_TTL_FIXTURES', 30),
            '#^/standings$#' => (int) env('API_FOOTBALL_RESPONSE_CACHE_TTL_STANDINGS', 120),
            '#^/teams$#' => (int) env('API_FOOTBALL_RESPONSE_CACHE_TTL_TEAMS', 900),
            '#^/leagues$#' => (int) env('API_FOOTBALL_RESPONSE_CACHE_TTL_LEAGUES', 3600),
        ],
    ],
];
