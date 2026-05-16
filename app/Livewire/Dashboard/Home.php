<?php

namespace App\Livewire\Dashboard;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\PoolRanking;
use App\Models\Prediction;
use App\Models\Standing;
use App\Models\StandingRow;
use App\Services\Predictions\PredictionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class Home extends Component
{
    public ?int $selectedPoolId = null;

    public function refreshMatches(): void {}

    public function refreshPoolRanking(): void {}

    protected function getListeners(): array
    {
        $listeners = [
            'echo:matches,MatchUpdated' => 'refreshMatches',
        ];

        if ($this->selectedPoolId) {
            $listeners["echo-private:pool.{$this->selectedPoolId},RankingUpdated"] = 'refreshPoolRanking';
        }

        return $listeners;
    }

    public function mount(): void
    {
        $code = strtoupper((string) request()->query(
            'competition',
            session('competition', config('football-data.default_competition_code', 'WC'))
        ));
        $stored = session('home_pool_'.$code);
        $this->selectedPoolId = is_numeric($stored) ? (int) $stored : null;
    }

    public function selectPool(?int $poolId): void
    {
        $code = strtoupper((string) request()->query(
            'competition',
            session('competition', config('football-data.default_competition_code', 'WC'))
        ));
        $this->selectedPoolId = $poolId;
        session(['home_pool_'.$code => $poolId]);
    }

    public function saveHeroPrediction($matchId, $homeScore, $awayScore): array
    {
        Log::info('dashboard.hero_prediction.save.attempt', [
            'user_id' => Auth::id(),
            'selected_pool_id' => $this->selectedPoolId,
            'match_id_raw' => $matchId,
            'home_score_raw' => $homeScore,
            'away_score_raw' => $awayScore,
        ]);

        if (! $this->selectedPoolId) {
            return ['ok' => false, 'msg' => 'Nenhum bolão selecionado.'];
        }

        try {
            $matchId = (int) $matchId;
            $homeScore = is_numeric($homeScore) ? (int) $homeScore : 0;
            $awayScore = is_numeric($awayScore) ? (int) $awayScore : 0;

            $pool  = Pool::findOrFail($this->selectedPoolId);
            $match = FootballMatch::findOrFail($matchId);

            // Validação final sempre no backend: janela de palpite do bolão.
            if ($match->isFinished() || $match->isPredictionLockedFor($pool)) {
                return ['ok' => false, 'msg' => 'Janela de palpite encerrada para este bolão.'];
            }

            app(PredictionService::class)->save(
                $pool,
                $match,
                Auth::user(),
                max(0, min(20, $homeScore)),
                max(0, min(20, $awayScore))
            );

            Log::info('dashboard.hero_prediction.save.success', [
                'user_id' => Auth::id(),
                'pool_id' => $pool->id,
                'match_id' => $match->id,
                'home_score' => max(0, min(20, $homeScore)),
                'away_score' => max(0, min(20, $awayScore)),
            ]);

            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('dashboard.hero_prediction.save.error', [
                'user_id' => Auth::id(),
                'selected_pool_id' => $this->selectedPoolId,
                'match_id' => $matchId,
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ['ok' => false, 'msg' => 'Não foi possível salvar o palpite agora.'];
        }
    }

    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            'GROUP_STAGE'        => 'Fase de Grupos',
            'ROUND_OF_16'        => 'Oitavas de Final',
            'QUARTER_FINALS'     => 'Quartas de Final',
            'SEMI_FINALS'        => 'Semifinal',
            'THIRD_PLACE'        => '3º Lugar',
            'FINAL'              => 'Final',
            'REGULAR_SEASON'     => 'Temporada Regular',
            'PLAY_OFF_ROUND_ONE' => 'Play-off',
            default              => $stage,
        };
    }

    public static function stageBadgeClass(string $stage): string
    {
        return match ($stage) {
            'ROUND_OF_16'    => 'phase-r16',
            'QUARTER_FINALS' => 'phase-qf',
            'SEMI_FINALS'    => 'phase-sf',
            'FINAL'          => 'phase-final',
            default          => 'phase-groups',
        };
    }

    public function render()
    {
        $userId = (int) Auth::id();
        $user   = Auth::user();

        $currentCompetitionCode = strtoupper((string) request()->query(
            'competition',
            session('competition', config('football-data.default_competition_code', 'WC'))
        ));

        if (! $user || ! $user->canAccessCompetition($currentCompetitionCode)) {
            $currentCompetitionCode = 'WC';
        }

        session(['competition' => $currentCompetitionCode]);

        $currentSeason          = (int)    config('football-data.competitions.'.$currentCompetitionCode.'.season', 0);
        $currentStage           = (string) config('football-data.competitions.'.$currentCompetitionCode.'.default_stage', 'GROUP_STAGE');
        $currentCompetitionName = (string) config('football-data.competitions.'.$currentCompetitionCode.'.name', $currentCompetitionCode);
        $competitionType        = (string) config('football-data.competitions.'.$currentCompetitionCode.'.type', 'cup');
        $standingsCriteriaLabel = match ($currentCompetitionCode) {
            'WC'    => 'Critério FIFA',
            'BSA'   => 'Pontos · Vitórias · SG · GP',
            default => 'Critério oficial',
        };

        $competitionScope = fn ($query) => $query
            ->whereHas('competition', fn ($q) => $q->where('code', $currentCompetitionCode))
            ->whereHas('season',      fn ($q) => $q->where('year', $currentSeason));

        /* ── Memberships ─────────────────────────────────────────── */
        $myMemberships = PoolMember::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with([
                'pool:id,name,slug,status,stage,competition_id,competition_season_id,points_exact_score,points_correct_result,points_correct_goals,correct_goals_mode,prediction_lock_minutes,allow_prediction_changes,allow_pending_member_predictions',
                'pool.competition:id,code',
            ])
            ->latest('id')
            ->get();

        $myMembershipsForComp = $myMemberships->filter(
            fn ($m) => $m->pool?->competition?->code === $currentCompetitionCode
        );

        // Auto-select when there is exactly one pool for this competition
        if (is_null($this->selectedPoolId) && $myMembershipsForComp->count() === 1) {
            $this->selectedPoolId = $myMembershipsForComp->first()->pool_id;
            session(['home_pool_'.$currentCompetitionCode => $this->selectedPoolId]);
        }

        // Invalidate selection if it no longer belongs to this competition
        if ($this->selectedPoolId && ! $myMembershipsForComp->firstWhere('pool_id', $this->selectedPoolId)) {
            $this->selectedPoolId = null;
        }

        $myPoolIds = $myMemberships->pluck('pool_id')->filter()->all();

        $myRankings = PoolRanking::query()
            ->whereIn('pool_id', $myPoolIds)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('pool_id');

        /* ── Matches ─────────────────────────────────────────────── */
        $teamWith = ['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'];

        $live = FootballMatch::query()
            ->where($competitionScope)
            ->whereIn('status', ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
            ->with($teamWith)
            ->orderBy('utc_date')
            ->get();

        $liveMinutes = [];
        foreach ($live as $match) {
            $liveMinutes[$match->id] = $this->resolveLiveMinute($match);
        }
        $liveMatchStats = [];
        foreach ($live as $match) {
            $liveMatchStats[$match->id] = $this->extractLiveStats($match);
        }

        $upcoming = FootballMatch::query()
            ->where($competitionScope)
            ->whereIn('status', ['TIMED', 'SCHEDULED'])
            ->with($teamWith)
            ->orderBy('utc_date')
            ->limit(8)
            ->get();

        $recent = FootballMatch::query()
            ->where($competitionScope)
            ->where('status', 'FINISHED')
            ->with(array_merge($teamWith, ['detail:id,football_match_id,payload']))
            ->orderByDesc('utc_date')
            ->limit(5)
            ->get();

        $matchLinks = [];
        foreach ($live->concat($upcoming)->concat($recent) as $match) {
            $url = route('matches.show', ['match' => $match->id]);
            $matchLinks[(int) $match->id] = $url;
        }

        /* ── Standings ───────────────────────────────────────────── */
        $standings = Standing::query()
            ->where($competitionScope)
            ->where('stage', $currentStage)
            ->where('type', 'TOTAL')
            ->with([
                'rows' => fn ($q) => $q->with('team:id,name,short_name,tla,crest')
                    ->orderByRaw('case when position is null then 1 else 0 end')
                    ->orderBy('position')
                    ->orderByDesc('points')
                    ->orderByDesc('goal_difference')
                    ->orderByDesc('goals_for'),
            ])
            ->orderByRaw('case when group_name is null then 1 else 0 end')
            ->orderBy('group_name')
            ->get();

        $groupStandings = $standings
            ->groupBy(fn (Standing $s) => $s->group_name ?: 'Classificação Geral')
            ->map(fn ($items) => $items->first())
            ->values();

        // WC fallback: split single "Geral" standing by group from match data
        if (
            $currentCompetitionCode === 'WC' &&
            $groupStandings->count() === 1 &&
            in_array($groupStandings->first()?->group_name, [null, 'Classificação Geral'], true)
        ) {
            $teamGroupMap = [];
            FootballMatch::query()
                ->where($competitionScope)
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->get(['home_team_id', 'away_team_id', 'group_name'])
                ->each(function ($gm) use (&$teamGroupMap) {
                    if ($gm->home_team_id) $teamGroupMap[$gm->home_team_id] ??= $gm->group_name;
                    if ($gm->away_team_id) $teamGroupMap[$gm->away_team_id] ??= $gm->group_name;
                });

            $rows = $groupStandings->first()?->rows ?? collect();

            if (! empty($teamGroupMap) && $rows->isNotEmpty()) {
                $groupStandings = $rows
                    ->groupBy(fn (StandingRow $row) => $teamGroupMap[$row->team_id] ?? 'Classificação Geral')
                    ->map(function ($groupRows, string $groupName) {
                        $sorted = $groupRows->sortBy([
                            fn (StandingRow $r) => is_null($r->position) ? 1 : 0,
                            fn (StandingRow $r) => (int) ($r->position ?? PHP_INT_MAX),
                            fn (StandingRow $r) => -(int) $r->points,
                            fn (StandingRow $r) => -(int) $r->goal_difference,
                            fn (StandingRow $r) => -(int) $r->goals_for,
                        ])->values();

                        return (object) ['group_name' => $groupName, 'rows' => $sorted];
                    })
                    ->sortBy('group_name')
                    ->values();
            }
        }

        /* ── Selected pool context ───────────────────────────────── */
        $selectedPool            = null;
        $selectedPoolRanking     = null;
        $selectedPoolMemberCount = 0;
        $selectedPoolTopRankings = collect();

        if ($this->selectedPoolId) {
            $selectedPool = $myMembershipsForComp->firstWhere('pool_id', $this->selectedPoolId)?->pool;

            if ($selectedPool) {
                $selectedPoolRanking = PoolRanking::where('pool_id', $this->selectedPoolId)
                    ->where('user_id', $userId)
                    ->first();

                $selectedPoolMemberCount = PoolMember::where('pool_id', $this->selectedPoolId)
                    ->where('status', 'active')
                    ->count();

                $selectedPoolTopRankings = PoolRanking::query()
                    ->where('pool_id', $this->selectedPoolId)
                    ->with('user:id,name,display_name')
                    ->orderByRaw('case when position is null then 1 else 0 end')
                    ->orderBy('position')
                    ->orderByDesc('points_total')
                    ->limit(5)
                    ->get();

                // Top sempre limitado a 5 itens.
                // Se o usuário estiver fora do top 5, ele ocupa o 5º elemento com sua posição real destacada.
                if ($selectedPoolRanking && (int) ($selectedPoolRanking->position ?? 0) > 5) {
                    $topFour = PoolRanking::query()
                        ->where('pool_id', $this->selectedPoolId)
                        ->with('user:id,name,display_name')
                        ->orderByRaw('case when position is null then 1 else 0 end')
                        ->orderBy('position')
                        ->orderByDesc('points_total')
                        ->limit(4)
                        ->get();

                    $myRanking = PoolRanking::query()
                        ->where('pool_id', $this->selectedPoolId)
                        ->where('user_id', $userId)
                        ->with('user:id,name,display_name')
                        ->first();

                    if ($myRanking) {
                        $myRanking->setAttribute('forced_fifth_slot', true);
                        $selectedPoolTopRankings = $topFour->concat(collect([$myRanking]))->values();
                    }
                }
            }
        }

        /* ── Hero match: first upcoming that matches the pool stage, else any upcoming ── */
        $heroMatch      = null;
        $heroPrediction = null;
        $heroCanPredict = false;
        $heroLockTime   = null;
        $heroLockedForPool = false;
        $heroStageMismatch = false;

        if ($upcoming->isNotEmpty()) {
            if ($selectedPool && $selectedPool->stage) {
                $heroMatch = $upcoming->firstWhere('stage', $selectedPool->stage) ?? $upcoming->first();
            } else {
                $heroMatch = $upcoming->first();
            }
        }

        if ($heroMatch && $selectedPool) {
            $heroPrediction = Prediction::where('pool_id', $this->selectedPoolId)
                ->where('user_id', $userId)
                ->where('football_match_id', $heroMatch->id)
                ->first();

            $heroStageMismatch = $selectedPool->stage && $heroMatch->stage
                && $heroMatch->stage !== $selectedPool->stage;
            $heroLockedForPool = $heroMatch->isFinished() || $heroMatch->isPredictionLockedFor($selectedPool);

            $heroCanPredict = ! $heroStageMismatch && ! $heroLockedForPool;

            $heroLockTime = $heroMatch->predictionLockTimeFor($selectedPool)
                ?->timezone('America/Sao_Paulo');
        }

        /* ── Predictions for recent results (pts chips) ──────────── */
        $recentPredictions = collect();
        if ($selectedPool && $recent->isNotEmpty()) {
            $recentPredictions = Prediction::where('pool_id', $this->selectedPoolId)
                ->where('user_id', $userId)
                ->whereIn('football_match_id', $recent->pluck('id')->all())
                ->get()
                ->keyBy('football_match_id');
        }

        /* ── Predictions for upcoming matches (status chips) ─────── */
        $upcomingPredictions = collect();
        if ($selectedPool && $upcoming->isNotEmpty()) {
            $upcomingPredictions = Prediction::where('pool_id', $this->selectedPoolId)
                ->where('user_id', $userId)
                ->whereIn('football_match_id', $upcoming->pluck('id')->all())
                ->get()
                ->keyBy('football_match_id');
        }

        /* ── Live match predictions by pool (current user) ──────── */
        $livePoolPredictions = collect();
        if ($live->isNotEmpty() && $myMembershipsForComp->isNotEmpty()) {
            $poolById = $myMembershipsForComp
                ->mapWithKeys(fn ($membership) => [$membership->pool_id => $membership->pool]);

            $livePredictions = Prediction::query()
                ->where('user_id', $userId)
                ->whereIn('pool_id', $poolById->keys()->all())
                ->whereIn('football_match_id', $live->pluck('id')->all())
                ->orderBy('pool_id')
                ->get();

            foreach ($live as $liveMatch) {
                $homeReal = is_numeric($liveMatch->home_score_full_time) ? (int) $liveMatch->home_score_full_time : null;
                $awayReal = is_numeric($liveMatch->away_score_full_time) ? (int) $liveMatch->away_score_full_time : null;

                $rows = $livePredictions
                    ->where('football_match_id', $liveMatch->id)
                    ->map(function (Prediction $prediction) use ($poolById, $homeReal, $awayReal) {
                        $pool = $poolById->get($prediction->pool_id);
                        if (! $pool) {
                            return null;
                        }

                        return [
                            'pool_id' => (int) $pool->id,
                            'pool_name' => (string) $pool->name,
                            'prediction' => (string) $prediction->home_score.'x'.(string) $prediction->away_score,
                            'points' => $this->calculatePerMatchPoints(
                                pool: $pool,
                                predictionHome: (int) $prediction->home_score,
                                predictionAway: (int) $prediction->away_score,
                                homeReal: $homeReal,
                                awayReal: $awayReal,
                            ),
                        ];
                    })
                    ->filter()
                    ->sortBy('pool_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                $livePoolPredictions[(int) $liveMatch->id] = $rows;
            }
        }

        return view('livewire.dashboard.home', compact(
            'myMemberships',
            'myMembershipsForComp',
            'myRankings',
            'upcoming',
            'live',
            'liveMinutes',
            'liveMatchStats',
            'recent',
            'groupStandings',
            'currentCompetitionCode',
            'currentCompetitionName',
            'competitionType',
            'standingsCriteriaLabel',
            'matchLinks',
            'selectedPool',
            'selectedPoolRanking',
            'selectedPoolMemberCount',
            'selectedPoolTopRankings',
            'heroMatch',
            'heroPrediction',
            'heroCanPredict',
            'heroLockTime',
            'heroLockedForPool',
            'heroStageMismatch',
            'recentPredictions',
            'upcomingPredictions',
            'livePoolPredictions',
        ));
    }

    /* ── Private helpers ─────────────────────────────────────────── */

    private function resolveLiveMinute(FootballMatch $match): ?int
    {
        if (! in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true)) {
            return null;
        }

        $apiShortStatus = strtoupper((string) data_get($match->raw_payload, 'api_football_status.short', ''));
        $apiElapsed     = data_get($match->raw_payload, 'api_football_status.elapsed');
        if (in_array($apiShortStatus, ['PST', 'TBD', 'SUSP', 'INT', 'NS'], true) && ! is_numeric($apiElapsed)) {
            return null;
        }

        $minute = data_get($match->raw_payload, 'minute');
        if (is_numeric($minute)) {
            return max(1, min(130, (int) $minute));
        }

        $trackedSeconds = (int) ($match->live_clock_accumulated_seconds ?? 0);
        if ($match->status === 'IN_PLAY' && $match->live_clock_anchor_at) {
            $trackedSeconds += max(0, $match->live_clock_anchor_at->diffInSeconds(now()->utc()));
        }
        if ($trackedSeconds > 0) {
            return max(1, min(130, (int) floor($trackedSeconds / 60) + 1));
        }

        if (! $match->utc_date) {
            return null;
        }

        $elapsed = $match->utc_date->copy()->timezone('America/Sao_Paulo')
            ->diffInMinutes(now('America/Sao_Paulo'), false);

        return max(1, min(130, $elapsed <= 0 ? 1 : $elapsed + 1));
    }

    private function extractLiveStats(FootballMatch $match): array
    {
        $payload = (array) ($match->raw_payload ?? []);

        $homeShots = data_get($payload, 'stats.shots.home');
        $awayShots = data_get($payload, 'stats.shots.away');
        if (!is_numeric($homeShots) || !is_numeric($awayShots)) {
            $homeShots = data_get($payload, 'api_football_statistics.home.shots_total');
            $awayShots = data_get($payload, 'api_football_statistics.away.shots_total');
        }

        $homePoss = data_get($payload, 'stats.possession.home');
        $awayPoss = data_get($payload, 'stats.possession.away');
        if (!is_numeric($homePoss) || !is_numeric($awayPoss)) {
            $homePoss = data_get($payload, 'api_football_statistics.home.possession');
            $awayPoss = data_get($payload, 'api_football_statistics.away.possession');
        }

        return [
            'shots_home' => is_numeric($homeShots) ? (int) $homeShots : null,
            'shots_away' => is_numeric($awayShots) ? (int) $awayShots : null,
            'poss_home' => is_numeric($homePoss) ? (int) $homePoss : null,
            'poss_away' => is_numeric($awayPoss) ? (int) $awayPoss : null,
        ];
    }

    private function calculatePerMatchPoints(
        Pool $pool,
        int $predictionHome,
        int $predictionAway,
        ?int $homeReal,
        ?int $awayReal,
    ): ?int {
        if ($homeReal === null || $awayReal === null) {
            return null;
        }

        $exactScorePoints = max(0, (int) ($pool->points_exact_score ?? 5));
        $correctResultPoints = max(0, (int) ($pool->points_correct_result ?? 3));
        $correctGoalsPoints = max(0, (int) ($pool->points_correct_goals ?? 1));

        $isExact = $predictionHome === $homeReal && $predictionAway === $awayReal;
        if ($isExact) {
            return $exactScorePoints;
        }

        $points = 0;
        if ($this->resultOf($homeReal, $awayReal) === $this->resultOf($predictionHome, $predictionAway)) {
            $points += $correctResultPoints;
            $hitHomeGoals = $predictionHome === $homeReal;
            $hitAwayGoals = $predictionAway === $awayReal;
            $correctGoalsMode = (string) ($pool->correct_goals_mode ?? 'both_teams');
            if ($correctGoalsMode === 'winner_only') {
                if ($homeReal > $awayReal && $hitHomeGoals) {
                    $points += $correctGoalsPoints;
                } elseif ($awayReal > $homeReal && $hitAwayGoals) {
                    $points += $correctGoalsPoints;
                }
            } else {
                if ($hitHomeGoals || $hitAwayGoals) {
                    $points += $correctGoalsPoints;
                }
            }
        }

        return $points;
    }

    private function resultOf(int $home, int $away): string
    {
        return $home > $away ? 'H' : ($home < $away ? 'A' : 'D');
    }
}
