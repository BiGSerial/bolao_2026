<?php

return [
    'minimum_supported_version' => env('PWA_MINIMUM_SUPPORTED_VERSION', env('APP_VERSION', 'dev')),
    'build_id' => env('PWA_BUILD_ID', env('APP_VERSION', 'dev')),
    'kill_switch' => (bool) env('PWA_KILL_SWITCH', false),
    'feature_flags' => [
        'pwa_enabled' => (bool) env('PWA_FEATURE_ENABLED', true),
        'offline_predictions_queue' => (bool) env('PWA_FEATURE_OFFLINE_PREDICTIONS_QUEUE', false),
        'push_notifications' => (bool) env('PWA_FEATURE_PUSH_NOTIFICATIONS', false),
        'realtime_rankings' => (bool) env('PWA_FEATURE_REALTIME_RANKINGS', false),
    ],
];
