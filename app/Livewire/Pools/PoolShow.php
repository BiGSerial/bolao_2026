<?php

namespace App\Livewire\Pools;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\Team;
use Illuminate\Support\Collection;
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
    public ?string $bulkGroup = null;
    public bool $showInstructions = false;
    /** @var array<string,string> */
    private array $countryNamesByTla = [];

    public function mount(Pool $pool): void
    {
        $this->pool = $pool;
        $this->pool->loadMissing(['competition:id,code', 'season:id,current_matchday']);
        $this->countryNamesByTla = config('country_names.by_tla', []);
        $this->assertMember();
    }

    public function teamDisplayName(?Team $team): string
    {
        if (! $team) {
            return 'A definir';
        }

        $tla = strtoupper((string) ($team->tla ?? ''));
        if ($tla !== '' && isset($this->countryNamesByTla[$tla])) {
            return $this->countryNamesByTla[$tla];
        }

        return (string) ($team->short_name ?: $team->name ?: 'A definir');
    }

    public function savePrediction(int $matchId, PredictionService $service): void
    {
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
            $this->dispatch('prediction-saved', matchId: $matchId);
        } catch (DomainException $e) {
            $this->addError('scores.'.$matchId, $e->getMessage());
        }
    }

    public function applyBulkPrediction(string $home, string $away, PredictionService $service): void
    {
        $this->assertMember();

        $h = max(0, min(30, (int) $home));
        $a = max(0, min(30, (int) $away));

        $matches = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->when($this->bulkGroup, fn ($q) => $q->where('group_name', $this->bulkGroup))
            ->get();

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
            if ($match->isPredictionLockedFor($this->pool)) {
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

    private function assertMember(): PoolMember
    {
        $isAdmin = (bool) Auth::user()?->is_admin;
        abort_if(! $isAdmin && $this->pool->status !== 'active', 403);

        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        return $member;
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
            return $match->isPredictionLockedFor($this->pool) ? 'bloqueado' : 'sem_palpite';
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
        return $match->isPredictionLockedFor($this->pool) ? 'bloqueado' : 'aberto';
    }

    public function render()
    {
        $userId = (int) Auth::id();

        $member = $this->pool->members()->where('user_id', $userId)->first();

        $matches = FootballMatch::query()
            ->where('competition_id', $this->pool->competition_id)
            ->where('competition_season_id', $this->pool->competition_season_id)
            ->where('stage', $this->pool->stage)
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->get();

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

        $predictions = Prediction::query()
            ->where('pool_id', $this->pool->id)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('football_match_id');

        foreach ($predictions as $prediction) {
            if (! isset($this->scores[$prediction->football_match_id])) {
                $this->scores[$prediction->football_match_id] = [
                    'home' => $prediction->home_score,
                    'away' => $prediction->away_score,
                ];
            }
        }

        $groupedMatches = $matches->groupBy(fn (FootballMatch $m) => $m->group_name ?: 'SEM GRUPO');

        if ($this->isBrasileiraoPool()) {
            $currentMatchday = $this->resolveCurrentMatchday($matches);
            $nearestTickerMatches = $matches
                ->filter(fn (FootballMatch $m) => $currentMatchday !== null && (int) ($m->matchday ?? 0) === $currentMatchday)
                ->sortBy(fn (FootballMatch $m) => ($m->local_date ?? $m->utc_date))
                ->values();
        } else {
            $nearestTickerMatches = $matches
                ->filter(fn (FootballMatch $m) => (int) ($m->matchday ?? 0) === 1)
                ->sortBy(fn (FootballMatch $m) => ($m->local_date ?? $m->utc_date))
                ->values();
        }

        $statusLabels = [];
        $liveMinutes = [];
        $predictionStatuses = [];
        foreach ($matches as $match) {
            $statusLabels[$match->id] = $this->matchStatusLabel($match->status);
            $liveMinutes[$match->id] = $this->resolveLiveMinute($match);
            $predictionStatuses[$match->id] = $this->predictionStatusLabel($match, $predictions->get($match->id));
        }

        $rankings = $this->pool->rankings()
            ->with('user:id,name,display_name,area')
            ->orderBy('position')
            ->get();

        $rankingRows = $rankings->isEmpty()
            ? $this->buildProvisionalRankingRows()
            : $rankings;

        $myRanking = $rankingRows->firstWhere('user_id', $userId);

        $totalMatches = $matches->count();
        $predictedCount = $predictions->count();

        $rankingColumns = [
            'exact_scores' => (int) ($this->pool->points_exact_score ?? 5) > 0,
            'correct_results' => (int) ($this->pool->points_correct_result ?? 3) > 0,
            'correct_goals' => (int) ($this->pool->points_correct_goals ?? 1) > 0,
        ];

        return view('livewire.pools.poolshow', compact(
            'member', 'groupedMatches', 'predictions', 'statusLabels',
            'liveMinutes', 'predictionStatuses', 'rankings', 'rankingRows', 'rankingColumns', 'myRanking', 'totalMatches', 'predictedCount', 'nearestTickerMatches'
        ));
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
            return max(1, min(130, (int) floor($trackedSeconds / 60) + 1));
        }

        if (! $match->utc_date) {
            return null;
        }

        $kickoff = $match->utc_date->copy()->timezone('America/Sao_Paulo');
        $elapsed = $kickoff->diffInMinutes(now('America/Sao_Paulo'), false);
        if ($elapsed <= 0) {
            return 1;
        }

        return max(1, min(130, $elapsed + 1));
    }

    private function isBrasileiraoPool(): bool
    {
        return strtoupper((string) ($this->pool->competition?->code ?? '')) === 'BSA';
    }

    private function resolveCurrentMatchday(Collection $matches): ?int
    {
        $configured = (int) ($this->pool->season?->current_matchday ?? 0);
        if ($configured > 0) {
            return $configured;
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
