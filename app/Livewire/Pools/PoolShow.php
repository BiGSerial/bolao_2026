<?php

namespace App\Livewire\Pools;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Services\Pools\LivePoolRankingService;
use App\Services\Predictions\PredictionService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class PoolShow extends Component
{
    public Pool $pool;
    public array $scores = [];
    public string $activeTab = 'jogos';
    public ?int $predictionMemberUserId = null;
    public bool $showAllRounds = true;
    public ?string $bulkDelimiter = null;
    public ?int $displayLeftRoundFixed = null;
    public ?int $displayRightRound = null;
    public ?string $summaryScopeKey = null;
    public bool $showInstructions = false;
    /** @var array<string,string> */
    private array $countryNamesByTla = [];

    public function mount(Pool $pool): void
    {
        $this->pool = $pool;
        $this->pool->loadMissing(['competition:id,code', 'season:id,current_matchday']);
        $this->countryNamesByTla = config('country_names.by_tla', []);
        $tab = strtolower((string) request()->query('tab', ''));
        if (in_array($tab, ['jogos', 'ranking', 'chat', 'resumo', 'info'], true)) {
            $this->activeTab = $tab;
        }
        $currentMember = $this->assertMember();

        $this->predictionMemberUserId = $this->resolveRequestedPredictionMember($currentMember);
    }

    public function teamDisplayName(?Team $team): string
    {
        if (! $team) {
            return 'A definir';
        }

        $tla = strtoupper((string) ($team->abbr3 ?? ''));
        if ($tla !== '' && isset($this->countryNamesByTla[$tla])) {
            return $this->countryNamesByTla[$tla];
        }

        return (string) ($team->localized_name ?: $team->short_name ?: $team->name ?: 'A definir');
    }

    public function savePrediction(int $matchId, PredictionService $service): void
    {
        $this->assertCanEditPredictions();
        $this->assertMember();

        $home = (int) data_get($this->scores, $matchId.'.home', 0);
        $away = (int) data_get($this->scores, $matchId.'.away', 0);

        if ($home < 0 || $away < 0 || $home > 30 || $away > 30) {
            $this->addError('scores.'.$matchId, 'Placar inválido.');
            return;
        }

        $match = FootballMatch::query()->findOrFail($matchId);

        try {
            $service->save($this->pool, $match, Auth::user(), $home, $away);
            $this->scores[$matchId] = ['home' => $home, 'away' => $away];
            $this->dispatch('prediction-saved', matchId: $matchId);
        } catch (DomainException $e) {
            $this->addError('scores.'.$matchId, $e->getMessage());
        }
    }

    public function applyBulkPrediction(string $home, string $away, PredictionService $service): void
    {
        $this->assertCanEditPredictions();
        $this->assertMember();

        $h = max(0, min(30, (int) $home));
        $a = max(0, min(30, (int) $away));

        $matchesQuery = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->orderBy('utc_date');

        $this->applyBulkDelimiterToMatchQuery($matchesQuery);
        $matches = $matchesQuery->get();

        if ($this->isBrasileiraoPool()) {
            $currentMatchday = $this->resolveCurrentMatchday($matches);
            if ($currentMatchday !== null) {
                $matches = $matches->filter(
                    fn (FootballMatch $match) => $match->matchday !== null
                        && (int) $match->matchday >= $currentMatchday
                        && (int) $match->matchday <= ($currentMatchday + 1)
                )->values();
            }
        }

        $saved = 0;
        foreach ($matches as $match) {
            if ($this->pool->isPredictionLockedFor($match)) {
                continue;
            }
            try {
                $service->save($this->pool, $match, Auth::user(), $h, $a);
                $this->scores[$match->id] = ['home' => $h, 'away' => $a];
                $saved++;
            } catch (DomainException) {
                continue;
            }
        }

        session()->flash('status', "Palpite {$h}x{$a} aplicado em {$saved} jogos.");
    }

    public function replicatePredictionsToOtherPools(PredictionService $service): void
    {
        $this->assertCanEditPredictions();
        $viewer = Auth::user();
        $this->assertMember();

        $sourceMatchIds = $this->resolveReplicationSourceMatchIds();
        if (empty($sourceMatchIds)) {
            session()->flash('status', 'Nenhum jogo da tabela atual está disponível para replicação.');
            return;
        }

        $sourcePredictions = Prediction::query()
            ->where('pool_id', $this->pool->id)
            ->where('user_id', (int) $viewer->id)
            ->whereIn('football_match_id', $sourceMatchIds)
            ->get();

        if ($sourcePredictions->isEmpty()) {
            session()->flash('status', 'Nenhum palpite preenchido foi encontrado na tabela atual para replicação.');
            return;
        }

        $targetPools = Pool::query()
            ->where('id', '!=', $this->pool->id)
            ->where('status', 'active')
            ->whereHas('members', function ($query) use ($viewer) {
                $query->where('user_id', (int) $viewer->id)
                    ->whereIn('status', ['active', 'pending']);
            })
            ->get();

        if ($targetPools->isEmpty()) {
            session()->flash('status', 'Você não participa de outros bolões ativos para replicar palpites.');
            return;
        }

        $matchesById = FootballMatch::query()
            ->whereIn('id', $sourcePredictions->pluck('football_match_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $replicated = 0;
        $skipped = 0;

        foreach ($targetPools as $targetPool) {
            foreach ($sourcePredictions as $sourcePrediction) {
                $match = $matchesById->get($sourcePrediction->football_match_id);
                if (! $match) {
                    $skipped++;
                    continue;
                }

                try {
                    $service->save(
                        $targetPool,
                        $match,
                        $viewer,
                        (int) $sourcePrediction->home_score,
                        (int) $sourcePrediction->away_score
                    );
                    $replicated++;
                } catch (DomainException) {
                    $skipped++;
                }
            }
        }

        if ($replicated === 0) {
            session()->flash('status', 'Nenhum palpite pôde ser replicado. As regras dos outros bolões bloquearam esta operação.');
            return;
        }

        $scopeLabel = $this->replicationSourceLabel();
        $scopeLabel = $scopeLabel !== '' ? " ({$scopeLabel})" : '';
        session()->flash('status', "Replicação concluída{$scopeLabel}: {$replicated} palpite(s) replicado(s) e {$skipped} ignorado(s) por regra de bolão.");
    }

    #[On('echo:matches,MatchUpdated')]
    #[On('echo:matches,MatchDetailUpdated')]
    public function refreshMatches(): void
    {
        $this->pool->refresh();
    }

    #[On('echo-private:pool.{pool.id},RankingUpdated')]
    #[On('echo-private:pool.{pool.id},MembersUpdated')]
    public function refreshPoolData(): void
    {
        $this->pool->refresh();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function enableAllRounds(): void
    {
        $this->showAllRounds = true;
    }

    public function enableFocusRounds(): void
    {
        $this->showAllRounds = false;
    }

    public function previousDisplayRound(): void
    {
        $rounds = $this->resolveDisplayRounds();
        $leftRound = $this->ensureFixedLeftRound($rounds);
        if ($leftRound === null) {
            return;
        }

        $index = (int) $rounds->search($leftRound);
        if ($index <= 0) {
            return;
        }

        $this->displayLeftRoundFixed = (int) $rounds->get($index - 1);
        $this->displayRightRound = null;
    }

    public function nextDisplayRound(): void
    {
        $rounds = $this->resolveDisplayRounds();
        $leftRound = $this->ensureFixedLeftRound($rounds);
        if ($leftRound === null) {
            return;
        }

        $index = (int) $rounds->search($leftRound);
        if ($index >= $rounds->count() - 1) {
            return;
        }

        $this->displayLeftRoundFixed = (int) $rounds->get($index + 1);
        $this->displayRightRound = null;
    }

    public function selectPreviousBulkRound(): void
    {
        $roundValues = $this->resolveBulkRoundValues();
        if ($roundValues->isEmpty()) {
            return;
        }

        if (! $roundValues->contains($this->bulkDelimiter)) {
            $this->bulkDelimiter = (string) $roundValues->last();
            return;
        }

        $currentIndex = (int) $roundValues->search($this->bulkDelimiter);
        if ($currentIndex <= 0) {
            return;
        }

        $this->bulkDelimiter = (string) $roundValues->get($currentIndex - 1);
    }

    public function selectNextBulkRound(): void
    {
        $roundValues = $this->resolveBulkRoundValues();
        if ($roundValues->isEmpty()) {
            return;
        }

        if (! $roundValues->contains($this->bulkDelimiter)) {
            $this->bulkDelimiter = (string) $roundValues->first();
            return;
        }

        $currentIndex = (int) $roundValues->search($this->bulkDelimiter);
        if ($currentIndex >= ($roundValues->count() - 1)) {
            return;
        }

        $this->bulkDelimiter = (string) $roundValues->get($currentIndex + 1);
    }

    public function previousSummaryScope(): void
    {
        $scopes = $this->resolveSummaryScopes($this->summaryBaseMatches(), $this->resolveSummaryMode($this->summaryBaseMatches()));
        if (count($scopes) <= 1) {
            return;
        }

        $keys = array_values(array_map(fn (array $scope) => (string) $scope['key'], $scopes));
        $currentIndex = array_search((string) $this->summaryScopeKey, $keys, true);
        if ($currentIndex === false) {
            $this->summaryScopeKey = $keys[0] ?? null;
            return;
        }

        if ($currentIndex <= 0) {
            return;
        }

        $this->summaryScopeKey = $keys[$currentIndex - 1] ?? $this->summaryScopeKey;
    }

    public function nextSummaryScope(): void
    {
        $scopes = $this->resolveSummaryScopes($this->summaryBaseMatches(), $this->resolveSummaryMode($this->summaryBaseMatches()));
        if (count($scopes) <= 1) {
            return;
        }

        $keys = array_values(array_map(fn (array $scope) => (string) $scope['key'], $scopes));
        $currentIndex = array_search((string) $this->summaryScopeKey, $keys, true);
        if ($currentIndex === false) {
            $this->summaryScopeKey = $keys[0] ?? null;
            return;
        }

        if ($currentIndex >= count($keys) - 1) {
            return;
        }

        $this->summaryScopeKey = $keys[$currentIndex + 1] ?? $this->summaryScopeKey;
    }

    private function assertMember(): PoolMember
    {
        $isAdmin = (bool) Auth::user()?->is_admin;
        abort_if(! $isAdmin && $this->pool->status !== 'active', 403);

        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        return $member;
    }

    private function assertCanEditPredictions(): void
    {
        $viewerUserId = (int) Auth::id();
        if ($this->predictionMemberUserId && $this->predictionMemberUserId !== $viewerUserId) {
            abort(403);
        }
    }

    private function resolveRequestedPredictionMember(PoolMember $currentMember): ?int
    {
        $requestedMemberUserId = request()->integer('member');
        if ($requestedMemberUserId <= 0) {
            return null;
        }

        if ($requestedMemberUserId === (int) Auth::id()) {
            return null;
        }

        $targetMember = $this->pool->members()
            ->where('user_id', $requestedMemberUserId)
            ->where('status', 'active')
            ->first();

        return $targetMember ? (int) $targetMember->user_id : null;
    }

    private function canViewOpenPredictionsFromOther(PoolMember $viewer): bool
    {
        if ((bool) Auth::user()?->is_admin) {
            return true;
        }

        return (string) $viewer->role === 'owner';
    }

    private function matchStatusLabel(string $status): string
    {
        return match ($status) {
            'TIMED', 'SCHEDULED' => 'Agendado',
            'PRE_MATCH' => 'Pré-Jogo',
            'IN_PLAY' => 'Ao Vivo',
            'PAUSED' => 'Intervalo',
            'EXTRA_TIME' => 'Prorrogação',
            'PENALTY_SHOOTOUT' => 'Pênaltis',
            'FINISHED' => 'Encerrado',
            'POSTPONED' => 'Adiado',
            'CANCELLED' => 'Cancelado',
            'SUSPENDED' => 'Suspenso',
            'AWARDED' => 'Decidido',
            default => ucfirst(strtolower($status)),
        };
    }

    private function predictionStatusLabel(FootballMatch $match, ?Prediction $prediction): string
    {
        if (! $prediction) {
            return $this->pool->isPredictionLockedFor($match) ? 'bloqueado' : 'sem_palpite';
        }
        if (! $prediction->eligible) {
            return 'inelegivel';
        }
        if ($prediction->calculated_at) {
            return 'calculado';
        }
        if ($match->isFinished()) {
            return 'finalizado';
        }
        return $this->pool->isPredictionLockedFor($match) ? 'bloqueado' : 'aberto';
    }

    public function render()
    {
        $viewerUserId = (int) Auth::id();
        $targetUserId = $this->predictionMemberUserId ?: $viewerUserId;

        $member = $this->pool->members()->where('user_id', $viewerUserId)->first();
        abort_if(! $member, 403);

        $isViewingOtherMember = $targetUserId !== $viewerUserId;
        $canEditPredictions = ! $isViewingOtherMember;
        $canViewOpenPredictionsFromOther = ! $isViewingOtherMember || $this->canViewOpenPredictionsFromOther($member);
        $predictionTargetName = null;
        if ($isViewingOtherMember) {
            $targetMember = $this->pool->members()
                ->where('user_id', $targetUserId)
                ->with('user:id,name,display_name')
                ->first();

            $predictionTargetName = (string) ($targetMember?->user?->display_name ?: $targetMember?->user?->name ?: 'participante');
        }

        $allStageMatches = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->with(['homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->get();

        $bulkDelimiters = $this->resolveBulkDelimiters($allStageMatches);
        $bulkRoundOptions = collect($bulkDelimiters)
            ->filter(fn (array $option) => str_starts_with((string) ($option['value'] ?? ''), 'matchday:'))
            ->values()
            ->all();
        $this->syncBulkDelimiterSelection($bulkRoundOptions, $bulkDelimiters);
        $bulkCurrentRoundLabel = collect($bulkRoundOptions)
            ->firstWhere('value', $this->bulkDelimiter)['label']
            ?? (collect($bulkDelimiters)->firstWhere('value', $this->bulkDelimiter)['label'] ?? null);
        $matches = $allStageMatches;

        $currentMatchday = $this->resolveCurrentMatchday($matches);

        $stageMatchIds = $matches->pluck('id')->all();

        $predictions = Prediction::query()
            ->where('pool_id', $this->pool->id)
            ->where('user_id', $targetUserId)
            ->whereIn('football_match_id', $stageMatchIds)
            ->get()
            ->keyBy('football_match_id');

        $predictionVisibility = [];
        foreach ($matches as $match) {
            $isVisible = ! $isViewingOtherMember
                || $canViewOpenPredictionsFromOther
                || $this->pool->isPredictionLockedFor($match);

            $predictionVisibility[$match->id] = $isVisible;
            if (! $isVisible) {
                $predictions->forget($match->id);
            }
        }

        if ($canEditPredictions) {
            foreach ($predictions as $prediction) {
                if (! isset($this->scores[$prediction->football_match_id])) {
                    $this->scores[$prediction->football_match_id] = [
                        'home' => $prediction->home_score,
                        'away' => $prediction->away_score,
                    ];
                }
            }
        }

        $displayRounds = $this->resolveDisplayRounds($matches);
        $displayLeftRound = $this->ensureFixedLeftRound($displayRounds, $currentMatchday);
        $displayRightRound = $this->resolveRightRound($displayRounds, $displayLeftRound);
        $displayRightCandidates = $displayRounds
            ->filter(fn (int $round) => $displayLeftRound !== null && $round > $displayLeftRound)
            ->values();
        $displayLeftIndex = $displayLeftRound !== null ? (int) $displayRounds->search($displayLeftRound) : -1;
        $canMoveDisplayPrev = $displayLeftIndex > 0;
        $canMoveDisplayNext = $displayLeftIndex >= 0 && $displayLeftIndex < ($displayRounds->count() - 1);
        $displayRoundWindow = collect([$displayLeftRound, $displayRightRound])->filter(fn ($round) => $round !== null)->values();

        $displayMatches = $matches->filter(function (FootballMatch $match) use ($displayRoundWindow) {
            if ($displayRoundWindow->isEmpty()) {
                return true;
            }

            return $match->matchday !== null && $displayRoundWindow->contains((int) $match->matchday);
        })->values();

        $groupedMatches = $displayMatches->groupBy(fn (FootballMatch $m) => $m->group_name ?: 'SEM GRUPO');

        $nearestTickerMatches = $matches
            ->filter(fn (FootballMatch $m) => $currentMatchday !== null && (int) ($m->matchday ?? 0) === $currentMatchday)
            ->sortBy(fn (FootballMatch $m) => $this->kickoffAtBrazil($m)?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        $statusLabels = [];
        $liveMinutes = [];
        $predictionStatuses = [];
        foreach ($matches as $match) {
            $statusLabels[$match->id] = $this->matchStatusLabel($match->status);
            $liveMinutes[$match->id] = $this->resolveLiveMinute($match);
            $predictionStatuses[$match->id] = $this->predictionStatusLabel($match, $predictions->get($match->id));
        }

        $summaryNavigationMode = $this->resolveSummaryMode($matches);
        $summaryScopes = $this->resolveSummaryScopes($matches, $summaryNavigationMode);
        $this->syncSummaryScopeSelection($summaryScopes);
        $summaryCurrentScope = collect($summaryScopes)->firstWhere('key', $this->summaryScopeKey);
        $summaryCurrentScopeLabel = (string) ($summaryCurrentScope['label'] ?? '');
        $summaryMatches = $matches
            ->filter(function (FootballMatch $match) use ($summaryNavigationMode) {
                if (! $this->summaryScopeKey) {
                    return true;
                }

                if ($summaryNavigationMode === 'groups') {
                    $groupKey = trim((string) ($match->group_name ?? 'SEM_GRUPO'));
                    return $groupKey === $this->summaryScopeKey;
                }

                if ($match->matchday === null) {
                    return false;
                }

                return ('round:'.(int) $match->matchday) === $this->summaryScopeKey;
            })
            ->sortByDesc(fn (FootballMatch $match) => $this->kickoffAtBrazil($match)?->getTimestamp() ?? 0)
            ->values();

        $rankings = $this->pool->rankings()
            ->with('user:id,name,display_name,area')
            ->orderBy('position')
            ->get();

        $liveRankingRows = app(LivePoolRankingService::class)->build($this->pool)->values();
        $baseRankingRows = $rankings->isEmpty()
            ? $this->buildProvisionalRankingRows()
            : $rankings;

        $livePointsByUser = $liveRankingRows
            ->mapWithKeys(fn ($row) => [(int) ($row->user_id ?? 0) => (int) ($row->points_total ?? 0)]);

        $rankingRows = $baseRankingRows
            ->map(function ($row) use ($livePointsByUser) {
                $consolidated = (int) ($row->points_total ?? 0);
                $live = (int) ($livePointsByUser->get((int) ($row->user_id ?? 0), $consolidated));
                $row->today_delta_points = $live - $consolidated;
                return $row;
            })
            ->values();

        $myRanking = $rankingRows->firstWhere('user_id', $targetUserId);

        $totalMatches = $matches->count();
        $predictedCount = $predictions->count();

        $rankingColumns = [
            'exact_scores' => (int) ($this->pool->points_exact_score ?? 5) > 0,
            'correct_results' => (int) ($this->pool->points_correct_result ?? 3) > 0,
            'correct_goals' => (int) ($this->pool->points_correct_goals ?? 1) > 0,
        ];

        return view('livewire.pools.poolshow', compact(
            'member', 'groupedMatches', 'predictions', 'statusLabels',
            'liveMinutes', 'predictionStatuses', 'rankings', 'rankingRows', 'rankingColumns', 'myRanking', 'totalMatches', 'predictedCount', 'nearestTickerMatches', 'currentMatchday',
            'isViewingOtherMember', 'canEditPredictions', 'predictionVisibility', 'predictionTargetName', 'bulkDelimiters', 'bulkRoundOptions', 'bulkCurrentRoundLabel',
            'displayLeftRound', 'displayRightRound', 'displayRightCandidates', 'canMoveDisplayPrev', 'canMoveDisplayNext',
            'summaryNavigationMode', 'summaryScopes', 'summaryCurrentScopeLabel', 'summaryMatches'
        ));
    }

    private function summaryBaseMatches(): Collection
    {
        return FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->orderBy('utc_date')
            ->get();
    }

    private function resolveSummaryMode(Collection $matches): string
    {
        $hasGroups = $matches
            ->pluck('group_name')
            ->filter(fn ($group) => is_string($group) && trim($group) !== '')
            ->isNotEmpty();

        return $hasGroups ? 'groups' : 'rounds';
    }

    private function resolveSummaryScopes(Collection $matches, string $mode): array
    {
        if ($mode === 'groups') {
            return $matches
                ->pluck('group_name')
                ->map(fn ($group) => trim((string) ($group ?: 'SEM_GRUPO')))
                ->filter(fn (string $group) => $group !== '')
                ->unique()
                ->sort()
                ->values()
                ->map(fn (string $group) => [
                    'key' => $group,
                    'label' => 'Grupo '.$group,
                ])
                ->all();
        }

        return $matches
            ->pluck('matchday')
            ->filter(fn ($matchday) => $matchday !== null && $matchday !== '')
            ->map(fn ($matchday) => (int) $matchday)
            ->unique()
            ->sort()
            ->values()
            ->map(fn (int $round) => [
                'key' => 'round:'.$round,
                'label' => 'Rodada '.$round,
            ])
            ->all();
    }

    public function kickoffAtBrazil(FootballMatch $match): ?\Carbon\CarbonInterface
    {
        return $match->kickoffAtBrazil();
    }

    private function syncSummaryScopeSelection(array $scopes): void
    {
        $keys = array_values(array_map(fn (array $scope) => (string) $scope['key'], $scopes));

        if (empty($keys)) {
            $this->summaryScopeKey = null;
            return;
        }

        if (! in_array((string) $this->summaryScopeKey, $keys, true)) {
            $this->summaryScopeKey = $keys[0];
        }
    }

    public function pointsForSummaryMatch(FootballMatch $match, ?Prediction $prediction): ?int
    {
        if ($match->status !== 'FINISHED') {
            return null;
        }

        if (! $prediction || ! $prediction->eligible) {
            return 0;
        }

        if ($prediction->points !== null) {
            return (int) $prediction->points;
        }

        $homeReal = $match->home_score_full_time;
        $awayReal = $match->away_score_full_time;
        if ($homeReal === null || $awayReal === null) {
            return null;
        }

        $exact = (int) $prediction->home_score === (int) $homeReal && (int) $prediction->away_score === (int) $awayReal;
        $exactScorePoints = max(0, (int) ($this->pool->points_exact_score ?? 5));
        $correctResultPoints = max(0, (int) ($this->pool->points_correct_result ?? 3));
        $correctGoalsPoints = max(0, (int) ($this->pool->points_correct_goals ?? 1));
        $correctGoalsMode = (string) ($this->pool->correct_goals_mode ?? 'both_teams');

        if ($exact) {
            return $exactScorePoints;
        }

        $points = 0;
        $realResult = $this->resultOf((int) $homeReal, (int) $awayReal);
        $predResult = $this->resultOf((int) $prediction->home_score, (int) $prediction->away_score);
        if ($realResult === $predResult) {
            $points += $correctResultPoints;
        }

        $hitHomeGoals = (int) $prediction->home_score === (int) $homeReal;
        $hitAwayGoals = (int) $prediction->away_score === (int) $awayReal;
        if ($correctGoalsMode === 'winner_only') {
            if ((int) $homeReal > (int) $awayReal && $hitHomeGoals) {
                $points += $correctGoalsPoints;
            } elseif ((int) $awayReal > (int) $homeReal && $hitAwayGoals) {
                $points += $correctGoalsPoints;
            }
        } else {
            if ($hitHomeGoals || $hitAwayGoals) {
                $points += $correctGoalsPoints;
            }
        }

        return $points;
    }

    public function summaryOutcomeType(FootballMatch $match, ?Prediction $prediction): string
    {
        if ($match->status !== 'FINISHED') {
            return 'pending';
        }

        if (! $prediction || ! $prediction->eligible) {
            return 'error';
        }

        $homeReal = $match->home_score_full_time;
        $awayReal = $match->away_score_full_time;
        if ($homeReal === null || $awayReal === null) {
            return 'pending';
        }

        $exact = (int) $prediction->home_score === (int) $homeReal && (int) $prediction->away_score === (int) $awayReal;
        if ($exact) {
            return 'exact';
        }

        $points = $this->pointsForSummaryMatch($match, $prediction);
        if ($points === null || $points <= 0) {
            return 'error';
        }

        $realResult = $this->resultOf((int) $homeReal, (int) $awayReal);
        $predResult = $this->resultOf((int) $prediction->home_score, (int) $prediction->away_score);

        return $realResult === $predResult ? 'result' : 'bonus';
    }

    private function resultOf(int $home, int $away): string
    {
        return $home > $away ? 'H' : ($home < $away ? 'A' : 'D');
    }

    private function resolveDisplayRounds(?Collection $matches = null): Collection
    {
        $source = $matches ?? FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->orderBy('utc_date')
            ->get();

        return $source
            ->pluck('matchday')
            ->filter(fn ($matchday) => $matchday !== null && $matchday !== '')
            ->map(fn ($matchday) => (int) $matchday)
            ->unique()
            ->sort()
            ->values();
    }

    private function ensureFixedLeftRound(Collection $rounds, ?int $preferredCurrentRound = null): ?int
    {
        if ($rounds->isEmpty()) {
            $this->displayLeftRoundFixed = null;
            return null;
        }

        if ($this->displayLeftRoundFixed !== null && $rounds->contains($this->displayLeftRoundFixed)) {
            return $this->displayLeftRoundFixed;
        }

        if ($preferredCurrentRound !== null && $rounds->contains($preferredCurrentRound)) {
            $this->displayLeftRoundFixed = $preferredCurrentRound;
            return $this->displayLeftRoundFixed;
        }

        $this->displayLeftRoundFixed = (int) $rounds->first();
        return $this->displayLeftRoundFixed;
    }

    private function resolveRightRound(Collection $rounds, ?int $leftRound = null): ?int
    {
        if ($rounds->isEmpty() || $leftRound === null) {
            $this->displayRightRound = null;
            return null;
        }

        $candidates = $rounds->filter(fn (int $round) => $round > $leftRound)->values();
        if ($candidates->isEmpty()) {
            $this->displayRightRound = null;
            return null;
        }

        $this->displayRightRound = (int) $candidates->first();
        return $this->displayRightRound;
    }

    private function resolveBulkRoundValues(): Collection
    {
        return FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->orderBy('utc_date')
            ->get()
            ->filter(fn (FootballMatch $match) => ! $match->isFinished() && ! $this->pool->isPredictionLockedFor($match))
            ->pluck('matchday')
            ->filter(fn ($matchday) => $matchday !== null && $matchday !== '')
            ->map(fn ($matchday) => 'matchday:'.((int) $matchday))
            ->unique()
            ->values();
    }

    private function syncBulkDelimiterSelection(array $bulkRoundOptions, array $bulkDelimiters): void
    {
        if (! empty($bulkRoundOptions)) {
            $validRoundValues = collect($bulkRoundOptions)->pluck('value')->all();
            if (! in_array($this->bulkDelimiter, $validRoundValues, true)) {
                $this->bulkDelimiter = $validRoundValues[0] ?? null;
            }

            return;
        }

        $validDelimiterValues = collect($bulkDelimiters)->pluck('value')->all();
        if (empty($validDelimiterValues)) {
            $this->bulkDelimiter = null;
            return;
        }

        if (! in_array($this->bulkDelimiter, $validDelimiterValues, true)) {
            $this->bulkDelimiter = $validDelimiterValues[0] ?? null;
        }
    }

    private function resolveBulkDelimiterParts(): array
    {
        $raw = trim((string) ($this->bulkDelimiter ?? ''));
        if ($raw === '' || ! str_contains($raw, ':')) {
            return [null, null];
        }

        [$type, $value] = explode(':', $raw, 2);
        $type = trim($type);
        $value = trim($value);

        if ($type === '' || $value === '') {
            return [null, null];
        }

        return [$type, $value];
    }

    private function applyBulkDelimiterToMatchQuery(Builder $query): void
    {
        [$type, $value] = $this->resolveBulkDelimiterParts();
        if (! $type || ! $value) {
            return;
        }

        match ($type) {
            'matchday' => $query->where('matchday', (int) $value),
            'group' => $query->where('group_name', $value),
            'date' => (function () use ($query, $value): void {
                // $value é uma data no fuso Brasil (ex: "2026-06-11").
                // utc_date armazena em UTC, então um jogo de 23h BRT tem utc_date no dia seguinte UTC.
                // Convertemos os limites do dia Brasil para UTC para capturar esses jogos corretamente.
                $startUtc = \Carbon\Carbon::parse($value, 'America/Sao_Paulo')->startOfDay()->utc();
                $endUtc   = \Carbon\Carbon::parse($value, 'America/Sao_Paulo')->endOfDay()->utc();
                $query->whereBetween('utc_date', [$startUtc->format('Y-m-d H:i:s'), $endUtc->format('Y-m-d H:i:s')]);
            })(),
            default => null,
        };
    }

    private function resolveBulkMatchIds(): ?array
    {
        [$type, $value] = $this->resolveBulkDelimiterParts();
        if (! $type || ! $value) {
            return null;
        }

        $query = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage);

        $this->applyBulkDelimiterToMatchQuery($query);

        return $query->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function resolveReplicationSourceMatchIds(): array
    {
        $matches = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->orderBy('utc_date')
            ->get();

        $displayRounds = $this->resolveDisplayRounds($matches);
        $leftRound = $this->ensureFixedLeftRound($displayRounds, $this->resolveCurrentMatchday($matches));
        $rightRound = $this->resolveRightRound($displayRounds, $leftRound);
        $roundWindow = collect([$leftRound, $rightRound])->filter(fn ($round) => $round !== null)->values();

        $filtered = $matches->filter(function (FootballMatch $match) use ($roundWindow) {
            if ($roundWindow->isEmpty()) {
                return true;
            }

            return $match->matchday !== null && $roundWindow->contains((int) $match->matchday);
        });

        return $filtered->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function replicationSourceLabel(): string
    {
        $matches = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->orderBy('utc_date')
            ->get();

        $displayRounds = $this->resolveDisplayRounds($matches);
        $leftRound = $this->ensureFixedLeftRound($displayRounds, $this->resolveCurrentMatchday($matches));
        $rightRound = $this->resolveRightRound($displayRounds, $leftRound);

        if ($leftRound === null) {
            return '';
        }

        if ($rightRound === null) {
            return 'rodada '.$leftRound;
        }

        return 'rodadas '.$leftRound.' e '.$rightRound;
    }

    private function bulkDelimiterLabel(): ?string
    {
        [$type, $value] = $this->resolveBulkDelimiterParts();
        if (! $type || ! $value) {
            return null;
        }

        return match ($type) {
            'all' => 'todos válidos',
            'matchday' => 'rodada '.$value,
            'group' => 'grupo '.$value,
            'date' => 'data '.$value,
            default => null,
        };
    }

    private function resolveBulkDelimiters(Collection $matches): array
    {
        $options = [];

        $openMatches = $matches->filter(
            fn (FootballMatch $match) => ! $match->isFinished() && ! $this->pool->isPredictionLockedFor($match)
        )->values();

        if ($openMatches->isNotEmpty()) {
            $options[] = [
                'value' => 'all:valid',
                'label' => 'Todos válidos',
            ];
        }

        $matchdays = $openMatches
            ->pluck('matchday')
            ->filter(fn ($matchday) => $matchday !== null && $matchday !== '')
            ->map(fn ($matchday) => (int) $matchday)
            ->unique()
            ->sort()
            ->values();

        foreach ($matchdays as $matchday) {
            $options[] = [
                'value' => 'matchday:'.$matchday,
                'label' => 'Rodada '.$matchday,
            ];
        }

        $groups = $openMatches
            ->pluck('group_name')
            ->filter(fn ($group) => is_string($group) && trim($group) !== '')
            ->map(fn ($group) => trim((string) $group))
            ->unique()
            ->sort()
            ->values();

        foreach ($groups as $group) {
            $options[] = [
                'value' => 'group:'.$group,
                'label' => 'Grupo '.$group,
            ];
        }

        if (! empty($options)) {
            return $options;
        }

        $dates = $openMatches
            ->map(fn (FootballMatch $match) => $match->kickoffAtBrazil()?->toDateString())
            ->filter(fn ($date) => is_string($date) && $date !== '')
            ->unique()
            ->sort()
            ->values();

        foreach ($dates as $date) {
            $display = \Carbon\Carbon::parse($date)->format('d/m/Y');
            $options[] = [
                'value' => 'date:'.$date,
                'label' => 'Data '.$display,
            ];
        }

        return $options;
    }

    private function resolveLiveMinute(FootballMatch $match): ?int
    {
        if (! in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true)) {
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
            return max(1, min(130, (int) floor($trackedSeconds / 60)));
        }

        $kickoff = $match->kickoffAtBrazil();
        if (! $kickoff) {
            return null;
        }

        $elapsed = $kickoff->diffInMinutes(now('America/Sao_Paulo'), false);
        if ($elapsed <= 0) {
            return 1;
        }

        return max(1, min(130, $elapsed));
    }

    private function isBrasileiraoPool(): bool
    {
        return strtoupper((string) ($this->pool->competition?->code ?? '')) === 'BSA';
    }

    private function resolveCurrentMatchday(Collection $matches): ?int
    {
        $nextOpenMatchday = $matches
            ->filter(fn (FootballMatch $match) => $match->matchday !== null && in_array($match->status, [
                'TIMED',
                'SCHEDULED',
                'PRE_MATCH',
                'IN_PLAY',
                'PAUSED',
                'EXTRA_TIME',
                'PENALTY_SHOOTOUT',
            ], true))
            ->map(fn (FootballMatch $match) => (int) $match->matchday)
            ->sort()
            ->first();

        if ($nextOpenMatchday !== null && $nextOpenMatchday > 0) {
            return (int) $nextOpenMatchday;
        }

        $configured = (int) ($this->pool->season?->current_matchday ?? 0);
        if ($configured > 0) {
            $availableRounds = $matches
                ->pluck('matchday')
                ->filter(fn ($matchday) => $matchday !== null && $matchday !== '')
                ->map(fn ($matchday) => (int) $matchday)
                ->unique()
                ->sort()
                ->values();

            if ($availableRounds->contains($configured)) {
                return $configured;
            }

            $nextAvailable = $availableRounds->first(fn (int $round) => $round > $configured);
            if ($nextAvailable !== null) {
                return (int) $nextAvailable;
            }

            if ($availableRounds->isNotEmpty()) {
                return (int) $availableRounds->last();
            }
        }

        $active = $matches
            ->filter(fn (FootballMatch $match) => $match->matchday !== null && in_array($match->status, ['IN_PLAY', 'PAUSED', 'TIMED', 'SCHEDULED'], true))
            ->map(fn (FootballMatch $match) => (int) $match->matchday);

        if ($active->isNotEmpty()) {
            return (int) $active->min();
        }

        $finished = $matches
            ->filter(fn (FootballMatch $match) => $match->matchday !== null && $match->status === 'FINISHED')
            ->map(fn (FootballMatch $match) => (int) $match->matchday);

        if ($finished->isNotEmpty()) {
            return ((int) $finished->max()) + 1;
        }

        return null;
    }

    private function buildProvisionalRankingRows(): Collection
    {
        return $this->pool->members()
            ->where('status', 'active')
            ->with('user:id,name,display_name,area')
            ->get()
            ->sortBy(fn (PoolMember $member) => mb_strtolower((string) ($member->user?->public_name ?? $member->user?->name ?? '')))
            ->values()
            ->map(function (PoolMember $member) {
                return (object) [
                    'user_id' => $member->user_id,
                    'user' => $member->user,
                    'position' => 1,
                    'points_total' => 0,
                    'exact_scores' => 0,
                    'correct_results' => 0,
                    'correct_home_goals' => 0,
                    'correct_away_goals' => 0,
                    'predictions_counted' => 0,
                ];
            });
    }
}
