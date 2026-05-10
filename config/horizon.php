<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', 'Bolão Copa'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'bolao'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Alertas de espera por fila (segundos)
    |--------------------------------------------------------------------------
    | Se um job ficar mais que N segundos aguardando, dispara LongWaitDetected.
    */
    'waits' => [
        'redis:mail'       => 30,
        'redis:scoring'    => 60,
        'redis:ranking'    => 120,
        'redis:api-funnel' => 300,
        'redis:api-sync'   => 300,
        'redis:default'    => 60,
    ],

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Jobs silenciados no dashboard (alto volume, pouco interesse visual)
    |--------------------------------------------------------------------------
    */
    'silenced' => [
        App\Jobs\RunCompetitionSyncJob::class,
        App\Jobs\RunCompetitionMatchDetailsSyncJob::class,
    ],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Configuração dos workers por ambiente
    |--------------------------------------------------------------------------
    |
    | Estratégia de filas (a ORDEM dentro de cada supervisor define prioridade):
    |
    |  supervisor-mail  → fila exclusiva de e-mail. Nunca compete com sync.
    |                     Garante entrega rápida independente do volume de API.
    |
    |  supervisor-jobs  → pontuação e ranking. Executam logo após partidas.
    |
    |  supervisor-api   → sync de dados das APIs externas (alto volume,
    |                     rate-limited). Isolado para não bloquear os demais.
    |
    | balance=auto + autoScalingStrategy=time → Horizon escala processos
    | automaticamente baseado no tempo de espera de cada fila.
    */
    'defaults' => [
        'supervisor-mail' => [
            'connection'           => 'redis',
            'queue'                => ['mail'],
            'balance'              => 'auto',
            'autoScalingStrategy'  => 'time',
            'minProcesses'         => 1,
            'maxProcesses'         => 3,
            'maxTime'              => 0,
            'maxJobs'              => 0,
            'memory'               => 128,
            'tries'                => 3,
            'timeout'              => 60,
            'nice'                 => 0,
        ],

        'supervisor-jobs' => [
            'connection'           => 'redis',
            'queue'                => ['scoring', 'ranking'],
            'balance'              => 'auto',
            'autoScalingStrategy'  => 'time',
            'minProcesses'         => 1,
            'maxProcesses'         => 3,
            'maxTime'              => 0,
            'maxJobs'              => 0,
            'memory'               => 128,
            'tries'                => 3,
            'timeout'              => 90,
            'nice'                 => 5,
        ],

        'supervisor-api' => [
            'connection'           => 'redis',
            'queue'                => ['api-funnel', 'api-sync'],
            'balance'              => 'auto',
            'autoScalingStrategy'  => 'time',
            'minProcesses'         => 1,
            'maxProcesses'         => 2,
            'maxTime'              => 0,
            'maxJobs'              => 0,
            'memory'               => 128,
            'tries'                => 3,
            'timeout'              => 120,
            'nice'                 => 10,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-mail' => [
                'minProcesses'      => 1,
                'maxProcesses'      => 4,
                'balanceMaxShift'   => 2,
                'balanceCooldown'   => 3,
            ],
            'supervisor-jobs' => [
                'minProcesses'      => 1,
                'maxProcesses'      => 4,
                'balanceMaxShift'   => 1,
                'balanceCooldown'   => 3,
            ],
            'supervisor-api' => [
                'minProcesses'      => 1,
                'maxProcesses'      => 2,
                'balanceMaxShift'   => 1,
                'balanceCooldown'   => 5,
            ],
        ],

        'local' => [
            'supervisor-mail' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-jobs' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],
            'supervisor-api' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'config/**/*.php',
        'routes',
        'composer.lock',
    ],
];
