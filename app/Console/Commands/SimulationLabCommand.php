<?php

namespace App\Console\Commands;

use App\Events\MatchDetailUpdated;
use App\Events\MatchUpdated;
use App\Events\PoolRankingUpdated;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\FootballMatchDetail;
use App\Models\Pool;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use App\Services\Pools\PoolRankingService;
use App\Services\Predictions\PredictionScoringService;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulationLabCommand extends Command
{
    protected $signature = 'sim:lab
        {action : bootstrap|tick|goal|finish|notify|status|set-status|cleanup}
        {--pool= : ID do bolao alvo}
        {--match= : ID da partida alvo}
        {--from-match= : ID de partida real para clonar no bootstrap}
        {--all : Limpa todos os artefatos simulados}
        {--allow-real-match : Permite operar em partida nao simulada (uso excepcional)}
        {--status= : Status da partida}
        {--team=home : Time do evento (home|away)}
        {--minute= : Minuto do evento (padrao: auto)}
        {--home-goals= : Placar do mandante}
        {--away-goals= : Placar do visitante}
        {--user= : ID do usuario para notify}
        {--title= : Titulo da notificacao}
        {--message= : Mensagem da notificacao}';

    protected $description = 'Laboratorio local para simular jogo ao vivo, gols, fechamento e notificacoes de teste.';

    public function handle(PredictionScoringService $scoringService, PoolRankingService $rankingService): int
    {
        if (App::environment('production')) {
            $this->error('sim:lab bloqueado em production.');

            return self::FAILURE;
        }

        return match ((string) $this->argument('action')) {
            'bootstrap' => $this->bootstrapScenario(),
            'tick' => $this->tickMatch(),
            'goal' => $this->registerGoal(),
            'finish' => $this->finishMatch($scoringService, $rankingService),
            'notify' => $this->sendTestNotification(),
            'status' => $this->showStatus(),
            'set-status' => $this->setMatchStatus(),
            'cleanup' => $this->cleanupScenario($rankingService),
            default => $this->invalidAction(),
        };
    }

    private function bootstrapScenario(): int
    {
        $pool = $this->resolvePool();
        if (! $pool) {
            return self::FAILURE;
        }

        [$competition, $season] = $this->resolveCompetitionContext($pool);
        $fromMatchId = $this->option('from-match');

        if ($fromMatchId !== null) {
            $source = FootballMatch::query()->with(['homeTeam', 'awayTeam'])->find((int) $fromMatchId);
            if (! $source) {
                $this->error('Partida de origem nao encontrada para clonagem.');

                return self::FAILURE;
            }
            $home = $source->homeTeam;
            $away = $source->awayTeam;
        } else {
            $home = Team::query()->firstOrCreate(
                ['provider' => 'simulator', 'external_id' => 900001],
                ['name' => 'Time Simulado A', 'short_name' => 'Sim A', 'tla' => 'SMA']
            );

            $away = Team::query()->firstOrCreate(
                ['provider' => 'simulator', 'external_id' => 900002],
                ['name' => 'Time Simulado B', 'short_name' => 'Sim B', 'tla' => 'SMB']
            );
        }

        $kickoffUtc = now()->utc()->subMinutes(12);

        $match = FootballMatch::query()->create([
            'provider' => 'simulator',
            'external_id' => random_int(100000, 999999),
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => $kickoffUtc,
            'local_date' => $kickoffUtc->copy()->timezone('America/Sao_Paulo'),
            'status' => 'IN_PLAY',
            'matchday' => 1,
            'stage' => $pool->stage,
            'group_name' => 'SIM-GROUP',
            'score_duration' => 'REGULAR',
            'home_score_full_time' => (int) ($this->option('home-goals') ?? 0),
            'away_score_full_time' => (int) ($this->option('away-goals') ?? 0),
            'in_play_started_at' => now()->subMinutes(12),
            'live_clock_anchor_at' => now()->subSeconds(30),
            'live_clock_accumulated_seconds' => 12 * 60,
            'last_updated_by_provider_at' => now(),
            'raw_payload' => ['simulator' => true],
        ]);

        FootballMatchDetail::query()->updateOrCreate(
            ['football_match_id' => $match->id],
            [
                'provider' => 'simulator',
                'external_id' => $match->id,
                'fetched_at' => now(),
                'payload' => $this->baseDetailPayload($match),
                'last_error' => null,
            ]
        );

        $members = $pool->members()->where('status', 'active')->get(['user_id']);
        foreach ($members as $member) {
            Prediction::query()->firstOrCreate(
                [
                    'pool_id' => $pool->id,
                    'user_id' => $member->user_id,
                    'football_match_id' => $match->id,
                ],
                [
                    'home_score' => random_int(0, 3),
                    'away_score' => random_int(0, 3),
                    'last_changed_at' => now()->subMinutes(20),
                    'eligible' => true,
                ]
            );
        }

        $match->refresh();
        $this->emitMatchRealtimeUpdates($match, true);

        $this->line('Cenario simulado criado com sucesso.');
        $this->table(['campo', 'valor'], [
            ['pool_id', (string) $pool->id],
            ['match_id', (string) $match->id],
            ['status', (string) $match->status],
            ['origem', $fromMatchId !== null ? 'clone de partida '.$fromMatchId : 'simulada'],
            ['placar', $match->home_score_full_time.' x '.$match->away_score_full_time],
            ['api_match_detail', '/api/v1/matches/'.$match->id.'/detail'],
            ['api_pool_predictions', '/api/v1/pools/'.$pool->id.'/matches/'.$match->id.'/predictions'],
            ['api_pool_rankings_live', '/api/v1/pools/'.$pool->id.'/rankings/live'],
        ]);

        return self::SUCCESS;
    }

    private function tickMatch(): int
    {
        $match = $this->resolveMatch();
        if (! $match) {
            return self::FAILURE;
        }
        if (! $this->assertMatchCanBeMutated($match)) {
            return self::FAILURE;
        }

        $minute = $this->resolveMinute($match);

        $match->update([
            'status' => 'IN_PLAY',
            'live_clock_accumulated_seconds' => $minute * 60,
            'live_clock_anchor_at' => now(),
            'last_updated_by_provider_at' => now(),
        ]);

        $match->refresh();
        $this->emitMatchRealtimeUpdates($match, false);

        $this->line("Partida {$match->id} atualizada para {$minute}' sem alterar placar.");

        return self::SUCCESS;
    }

    private function registerGoal(): int
    {
        $match = $this->resolveMatch();
        if (! $match) {
            return self::FAILURE;
        }
        if (! $this->assertMatchCanBeMutated($match)) {
            return self::FAILURE;
        }

        $team = strtolower((string) $this->option('team')) === 'away' ? 'away' : 'home';
        $minute = $this->resolveMinute($match);

        $homeGoals = $this->option('home-goals');
        $awayGoals = $this->option('away-goals');

        $newHome = $homeGoals !== null ? (int) $homeGoals : (int) $match->home_score_full_time;
        $newAway = $awayGoals !== null ? (int) $awayGoals : (int) $match->away_score_full_time;

        if ($homeGoals === null && $awayGoals === null) {
            if ($team === 'home') {
                $newHome++;
            } else {
                $newAway++;
            }
        }

        $match->update([
            'status' => 'IN_PLAY',
            'home_score_full_time' => $newHome,
            'away_score_full_time' => $newAway,
            'live_clock_accumulated_seconds' => $minute * 60,
            'live_clock_anchor_at' => now(),
            'last_updated_by_provider_at' => now(),
        ]);

        $this->appendGoalEvent($match, $team, $minute);

        $match->refresh();
        $this->emitMatchRealtimeUpdates($match, true);

        $this->line("Gol registrado para {$team} em {$minute}' | placar: {$newHome} x {$newAway}");

        return self::SUCCESS;
    }

    private function finishMatch(PredictionScoringService $scoringService, PoolRankingService $rankingService): int
    {
        $match = $this->resolveMatch();
        if (! $match) {
            return self::FAILURE;
        }
        if (! $this->assertMatchCanBeMutated($match)) {
            return self::FAILURE;
        }

        $home = (int) ($this->option('home-goals') ?? $match->home_score_full_time ?? 0);
        $away = (int) ($this->option('away-goals') ?? $match->away_score_full_time ?? 0);

        $match->update([
            'status' => 'FINISHED',
            'home_score_full_time' => $home,
            'away_score_full_time' => $away,
            'finished_at' => now(),
            'last_updated_by_provider_at' => now(),
            'score_winner' => $home === $away ? 'DRAW' : ($home > $away ? 'HOME_TEAM' : 'AWAY_TEAM'),
            'score_duration' => 'REGULAR',
        ]);

        Prediction::query()
            ->where('football_match_id', $match->id)
            ->with(['pool', 'footballMatch'])
            ->chunkById(200, function ($predictions) use ($scoringService): void {
                foreach ($predictions as $prediction) {
                    $scoringService->calculate($prediction);
                }
            });

        $poolIds = Prediction::query()
            ->where('football_match_id', $match->id)
            ->distinct()
            ->pluck('pool_id');

        foreach ($poolIds as $poolId) {
            $pool = Pool::query()->find($poolId);
            if ($pool) {
                $rankingService->recalculate($pool);
                PoolRankingUpdated::dispatch($pool);
            }
        }

        $match->refresh();
        $this->emitMatchRealtimeUpdates($match, true);
        $this->notifyPoolsFromMatch($match, "Partida finalizada: {$home} x {$away}", 'Resultado consolidado no simulador local.');

        $this->line("Partida {$match->id} finalizada e rankings recalculados.");

        return self::SUCCESS;
    }

    private function sendTestNotification(): int
    {
        $title = (string) ($this->option('title') ?? 'Notificacao de teste');
        $message = (string) ($this->option('message') ?? 'Mensagem gerada pelo simulador local.');

        $userId = $this->option('user');
        if ($userId !== null) {
            $user = User::query()->find((int) $userId);
            if (! $user) {
                $this->error('Usuario nao encontrado.');

                return self::FAILURE;
            }

            $user->notify(new SimulationDatabaseNotification($title, $message));
            $this->line("Notificacao enviada ao usuario {$user->id}.");

            return self::SUCCESS;
        }

        $pool = $this->resolvePool();
        if (! $pool) {
            return self::FAILURE;
        }

        $users = User::query()
            ->whereIn('id', $pool->members()->where('status', 'active')->pluck('user_id'))
            ->get();

        foreach ($users as $user) {
            $user->notify(new SimulationDatabaseNotification($title, $message));
        }

        $this->line("Notificacao enviada para {$users->count()} membro(s) ativos do bolao {$pool->id}.");

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $match = $this->resolveMatch();
        if (! $match) {
            return self::FAILURE;
        }

        $detail = FootballMatchDetail::query()->where('football_match_id', $match->id)->first();
        $events = (array) data_get($detail?->payload, '_api_football.events', []);

        $this->table(['campo', 'valor'], [
            ['match_id', (string) $match->id],
            ['status', (string) $match->status],
            ['placar', (int) $match->home_score_full_time.' x '.(int) $match->away_score_full_time],
            ['minuto', (string) floor(((int) $match->live_clock_accumulated_seconds) / 60)],
            ['eventos', (string) count($events)],
            ['ultima_atualizacao', (string) optional($match->last_updated_by_provider_at)?->toDateTimeString()],
        ]);

        return self::SUCCESS;
    }

    private function setMatchStatus(): int
    {
        $match = $this->resolveMatch();
        if (! $match) {
            return self::FAILURE;
        }
        if (! $this->assertMatchCanBeMutated($match)) {
            return self::FAILURE;
        }

        $status = strtoupper(trim((string) $this->option('status')));
        $allowed = [
            'TIMED', 'SCHEDULED', 'PRE_MATCH', 'IN_PLAY', 'PAUSED',
            'EXTRA_TIME', 'PENALTY_SHOOTOUT', 'FINISHED', 'AWARDED',
            'SUSPENDED', 'POSTPONED', 'CANCELLED',
        ];
        if ($status === '' || ! in_array($status, $allowed, true)) {
            $this->error('Status invalido. Use --status com um valor permitido.');

            return self::FAILURE;
        }

        $attributes = [
            'status' => $status,
            'last_updated_by_provider_at' => now(),
        ];
        if ($status === 'IN_PLAY' && ! $match->in_play_started_at) {
            $attributes['in_play_started_at'] = now();
        }
        if (in_array($status, ['FINISHED', 'AWARDED'], true)) {
            $attributes['finished_at'] = now();
        }

        $match->update($attributes);
        $match->refresh();
        $this->emitMatchRealtimeUpdates($match, true);

        $this->line("Status da partida {$match->id} atualizado para {$status}.");

        return self::SUCCESS;
    }

    private function cleanupScenario(PoolRankingService $rankingService): int
    {
        $all = (bool) $this->option('all');
        $matchIds = collect();
        $targetMatches = collect();

        if ($all) {
            $targetMatches = FootballMatch::query()
                ->where('provider', 'simulator')
                ->get(['id']);
            $matchIds = $targetMatches
                ->pluck('id');
        } else {
            $match = $this->resolveMatch();
            if (! $match) {
                return self::FAILURE;
            }
            $targetMatches = collect([$match]);
            $matchIds = collect([$match->id]);
        }

        if ($matchIds->isEmpty()) {
            $this->line('Nenhuma partida simulada encontrada para limpeza.');

            return self::SUCCESS;
        }

        $simulatedMatchIds = $targetMatches
            ->filter(fn (FootballMatch $m) => $m->provider === 'simulator')
            ->pluck('id')
            ->values();

        if ($simulatedMatchIds->isEmpty()) {
            $this->warn('A partida informada nao e simulada (provider != simulator). Nada para remover no cleanup.');
            $this->line('Dica: use bootstrap para criar partida simulada e opere nela.');

            return self::SUCCESS;
        }

        $poolIds = Prediction::query()
            ->whereIn('football_match_id', $simulatedMatchIds)
            ->distinct()
            ->pluck('pool_id');

        $deletedMatches = 0;
        DB::transaction(function () use ($simulatedMatchIds, &$deletedMatches): void {
            Prediction::query()->whereIn('football_match_id', $simulatedMatchIds)->delete();
            FootballMatchDetail::query()->whereIn('football_match_id', $simulatedMatchIds)->delete();
            $deletedMatches = FootballMatch::query()
                ->whereIn('id', $simulatedMatchIds)
                ->where('provider', 'simulator')
                ->delete();

            DB::table('notifications')
                ->where('data', 'like', '%"kind":"simulation"%')
                ->delete();
        });

        foreach ($poolIds as $poolId) {
            $pool = Pool::query()->find($poolId);
            if ($pool) {
                $rankingService->recalculate($pool);
                PoolRankingUpdated::dispatch($pool);
            }
        }

        $this->line('Limpeza concluida.');
        $this->table(['campo', 'valor'], [
            ['matches_removidas', (string) $deletedMatches],
            ['pools_recalculados', (string) $poolIds->count()],
        ]);

        return self::SUCCESS;
    }

    private function resolvePool(): ?Pool
    {
        $poolId = $this->option('pool');
        if ($poolId !== null) {
            $pool = Pool::query()->find((int) $poolId);
        } else {
            $pool = Pool::query()->orderBy('id')->first();
        }

        if (! $pool) {
            $this->error('Nenhum bolao encontrado. Crie um bolao antes de usar o simulador.');

            return null;
        }

        return $pool;
    }

    private function resolveCompetitionContext(Pool $pool): array
    {
        $competition = Competition::query()->find($pool->competition_id);
        if (! $competition) {
            $competition = Competition::query()->firstOrCreate(
                ['provider' => 'simulator', 'external_id' => 990001],
                ['code' => 'SIM', 'name' => 'Competicao Simulada', 'type' => 'LEAGUE']
            );
            $pool->competition_id = $competition->id;
            $pool->save();
        }

        $season = CompetitionSeason::query()->find($pool->competition_season_id);
        if (! $season) {
            $season = CompetitionSeason::query()->firstOrCreate(
                ['provider' => 'simulator', 'external_id' => 990101],
                [
                    'competition_id' => $competition->id,
                    'year' => (int) now()->year,
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->endOfYear()->toDateString(),
                ]
            );
            $pool->competition_season_id = $season->id;
            $pool->save();
        }

        return [$competition, $season];
    }

    private function resolveMatch(): ?FootballMatch
    {
        $matchId = $this->option('match');
        $query = FootballMatch::query()->with(['homeTeam', 'awayTeam', 'detail']);

        if ($matchId !== null) {
            $match = $query->find((int) $matchId);
        } else {
            $match = $query->where('provider', 'simulator')->latest('id')->first();
        }

        if (! $match) {
            $this->error('Partida nao encontrada. Execute: php artisan sim:lab bootstrap');

            return null;
        }

        return $match;
    }

    private function resolveMinute(FootballMatch $match): int
    {
        $minuteOption = $this->option('minute');
        if ($minuteOption !== null) {
            return max(1, (int) $minuteOption);
        }

        $current = (int) floor(((int) $match->live_clock_accumulated_seconds) / 60);

        return max(1, $current + 1);
    }

    private function baseDetailPayload(FootballMatch $match): array
    {
        $homeName = (string) ($match->homeTeam?->name ?? 'Casa');
        $awayName = (string) ($match->awayTeam?->name ?? 'Visitante');

        return [
            '_api_football' => [
                'lineups' => [
                    [
                        'team' => ['name' => $homeName],
                        'formation' => '4-3-3',
                        'coach' => ['name' => 'Tecnico Simulado A'],
                        'startXI' => [],
                        'substitutes' => [],
                    ],
                    [
                        'team' => ['name' => $awayName],
                        'formation' => '4-4-2',
                        'coach' => ['name' => 'Tecnico Simulado B'],
                        'startXI' => [],
                        'substitutes' => [],
                    ],
                ],
                'statistics' => [
                    [
                        'team' => ['name' => $homeName],
                        'statistics' => [
                            ['type' => 'Ball Possession', 'value' => '50%'],
                            ['type' => 'Total Shots', 'value' => 1],
                            ['type' => 'Shots on Goal', 'value' => 1],
                        ],
                    ],
                    [
                        'team' => ['name' => $awayName],
                        'statistics' => [
                            ['type' => 'Ball Possession', 'value' => '50%'],
                            ['type' => 'Total Shots', 'value' => 1],
                            ['type' => 'Shots on Goal', 'value' => 1],
                        ],
                    ],
                ],
                'events' => [],
            ],
        ];
    }

    private function appendGoalEvent(FootballMatch $match, string $team, int $minute): void
    {
        $detail = FootballMatchDetail::query()->firstOrCreate(
            ['football_match_id' => $match->id],
            [
                'provider' => 'simulator',
                'external_id' => $match->id,
                'payload' => $this->baseDetailPayload($match),
                'fetched_at' => now(),
            ]
        );

        $payload = $detail->payload ?? $this->baseDetailPayload($match);
        $events = (array) data_get($payload, '_api_football.events', []);

        $teamName = $team === 'home'
            ? (string) ($match->homeTeam?->name ?? 'Casa')
            : (string) ($match->awayTeam?->name ?? 'Visitante');

        $events[] = [
            'time' => ['elapsed' => $minute, 'extra' => null],
            'team' => ['name' => $teamName],
            'player' => ['name' => 'Jogador '.Str::upper(Str::random(3))],
            'assist' => ['name' => 'Assist '.Str::upper(Str::random(3))],
            'type' => 'Goal',
            'detail' => 'Normal Goal',
            'comments' => null,
        ];

        data_set($payload, '_api_football.events', $events);
        $detail->update([
            'payload' => $payload,
            'fetched_at' => now(),
            'last_error' => null,
        ]);
    }

    private function emitMatchRealtimeUpdates(FootballMatch $match, bool $includeDetailEvent): void
    {
        // No contexto do CLI o worker pode não estar processando a fila broadcast.
        // Forçamos dispatch síncrono para que o evento chegue ao Reverb imediatamente.
        $prev = config('queue.default');
        config(['queue.default' => 'sync']);

        try {
            MatchUpdated::dispatch($match);
            if ($includeDetailEvent) {
                MatchDetailUpdated::dispatch($match);
            }
        } finally {
            config(['queue.default' => $prev]);
        }

        $this->line("  → broadcast: MatchUpdated" . ($includeDetailEvent ? ' + MatchDetailUpdated' : '') . ' (sync)');
    }

    private function notifyPoolsFromMatch(FootballMatch $match, string $title, string $message): void
    {
        $poolIds = Prediction::query()
            ->where('football_match_id', $match->id)
            ->distinct()
            ->pluck('pool_id');

        if ($poolIds->isEmpty()) {
            return;
        }

        $userIds = collect();
        foreach ($poolIds as $poolId) {
            $ids = Pool::query()
                ->find($poolId)?->members()
                ->where('status', 'active')
                ->pluck('user_id') ?? collect();
            $userIds = $userIds->merge($ids);
        }

        $users = User::query()->whereIn('id', $userIds->unique()->values())->get();
        foreach ($users as $user) {
            $user->notify(new SimulationDatabaseNotification($title, $message));
        }
    }

    private function invalidAction(): int
    {
        $this->error('Acao invalida. Use: bootstrap|tick|goal|finish|notify|status|set-status|cleanup');

        return self::FAILURE;
    }

    private function assertMatchCanBeMutated(FootballMatch $match): bool
    {
        if ($match->provider === 'simulator') {
            return true;
        }

        if ((bool) $this->option('allow-real-match')) {
            $this->warn("Operando em partida real (provider={$match->provider}).");

            return true;
        }

        $this->error('Partida nao simulada detectada. Para seguranca, clone com --from-match ou use --allow-real-match explicitamente.');

        return false;
    }
}

class SimulationDatabaseNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'kind' => 'simulation',
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
