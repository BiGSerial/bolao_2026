<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME', 'BolãoVF'),

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
    |
    | Para o bolão com placar ao vivo, broadcast/scoring/ranking precisam
    | alertar rápido. API e mail podem ter tolerância maior.
    |
    */

    'waits' => [
        'redis:broadcast'  => 5,
        'redis:scoring'    => 10,
        'redis:ranking'    => 15,
        'redis:api-funnel' => 30,
        'redis:api-sync'   => 120,
        'redis:mail'       => 60,
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
    | Jobs silenciados no dashboard
    |--------------------------------------------------------------------------
    |
    | Jobs de sincronização de alto volume podem poluir o dashboard.
    |
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
    | Estratégia para bolão ao vivo:
    |
    |  1. broadcast    -> resposta visual imediata no navegador
    |  2. scoring      -> recalcula pontuação dos palpites
    |  3. ranking      -> atualiza classificação
    |  4. api-funnel   -> organiza entrada de dados externos
    |  5. api-sync     -> sincronizações mais pesadas/rate-limited
    |  6. mail         -> e-mails, avisos e notificações não críticas
    |  7. default      -> fila de segurança para jobs sem onQueue()
    |
    */

    'defaults' => [

        /*
        |--------------------------------------------------------------------------
        | Broadcast
        |--------------------------------------------------------------------------
        |
        | Fila mais sensível do sistema ao vivo.
        | Deve ficar sempre com workers prontos.
        |
        */

        'supervisor-broadcast' => [
            'connection'          => 'redis',
            'queue'               => ['broadcast'],
            'balance'             => 'simple',
            'minProcesses'        => 3,
            'maxProcesses'        => 5,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 64,
            'tries'               => 2,
            'timeout'             => 15,
            'nice'                => 0,
        ],

        /*
        |--------------------------------------------------------------------------
        | Scoring
        |--------------------------------------------------------------------------
        |
        | Recalcula pontos após mudanças em partidas/eventos.
        |
        */

        'supervisor-scoring' => [
            'connection'          => 'redis',
            'queue'               => ['scoring'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 2,
            'maxProcesses'        => 5,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 1,
        ],

        /*
        |--------------------------------------------------------------------------
        | Ranking
        |--------------------------------------------------------------------------
        |
        | Atualiza ranking geral, grupos, rodadas e competições.
        |
        */

        'supervisor-ranking' => [
            'connection'          => 'redis',
            'queue'               => ['ranking'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 4,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 3,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 2,
        ],

        /*
        |--------------------------------------------------------------------------
        | API Funnel
        |--------------------------------------------------------------------------
        |
        | Recebe, organiza e distribui dados vindos da API externa.
        | Deve ser rápido, mas sem atropelar o tempo real.
        |
        */

        'supervisor-api-funnel' => [
            'connection'          => 'redis',
            'queue'               => ['api-funnel'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 3,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 5,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 4,
        ],

        /*
        |--------------------------------------------------------------------------
        | API Sync
        |--------------------------------------------------------------------------
        |
        | Sincronizações externas mais pesadas e sujeitas a rate limit.
        | Controlada para não roubar CPU do placar ao vivo.
        |
        */

        'supervisor-api-sync' => [
            'connection'          => 'redis',
            'queue'               => ['api-sync'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 2,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 10,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 120,
            'nice'                => 7,
        ],

        /*
        |--------------------------------------------------------------------------
        | Mail
        |--------------------------------------------------------------------------
        |
        | E-mails são importantes, mas não devem competir com resultado ao vivo.
        |
        */

        'supervisor-mail' => [
            'connection'          => 'redis',
            'queue'               => ['mail'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 3,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 5,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 8,
        ],

        /*
        |--------------------------------------------------------------------------
        | Default
        |--------------------------------------------------------------------------
        |
        | Fila de segurança para jobs que eventualmente forem enviados sem onQueue().
        |
        */

        'supervisor-default' => [
            'connection'          => 'redis',
            'queue'               => ['default'],
            'balance'             => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses'        => 1,
            'maxProcesses'        => 1,
            'balanceMaxShift'     => 1,
            'balanceCooldown'     => 10,
            'maxTime'             => 0,
            'maxJobs'             => 0,
            'memory'              => 128,
            'tries'               => 3,
            'timeout'             => 60,
            'nice'                => 10,
        ],
    ],

    'environments' => [

        'production' => [

            'supervisor-broadcast' => [
                'minProcesses' => 3,
                'maxProcesses' => 5,
            ],

            'supervisor-scoring' => [
                'minProcesses'    => 2,
                'maxProcesses'    => 5,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'supervisor-ranking' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'supervisor-api-funnel' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],

            'supervisor-api-sync' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 10,
            ],

            'supervisor-mail' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 5,
            ],

            'supervisor-default' => [
                'minProcesses'    => 1,
                'maxProcesses'    => 1,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 10,
            ],
        ],

        'local' => [

            'supervisor-broadcast' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],

            'supervisor-scoring' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],

            'supervisor-ranking' => [
                'minProcesses' => 1,
                'maxProcesses' => 2,
            ],

            'supervisor-api-funnel' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],

            'supervisor-api-sync' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],

            'supervisor-mail' => [
                'minProcesses' => 1,
                'maxProcesses' => 1,
            ],

            'supervisor-default' => [
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
