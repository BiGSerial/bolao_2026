<?php

namespace App\Livewire\Pools;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\Team;
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
            ->where('stage', $this->pool->stage)
            ->when($this->bulkGroup, fn ($q) => $q->where('group_name', $this->bulkGroup))
            ->get();

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
    public function refreshMatches(): void
    {
        $this->pool->refresh();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    private function assertMember(): PoolMember
    {
        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        return $member;
    }

    private function matchStatusLabel(string $status): string
    {
        return match ($status) {
            'TIMED', 'SCHEDULED' => 'Agendado',
            'IN_PLAY' => 'Ao Vivo',
            'PAUSED' => 'Intervalo',
            'FINISHED' => 'Encerrado',
            'POSTPONED' => 'Adiado',
            'CANCELLED' => 'Cancelado',
            'SUSPENDED' => 'Suspenso',
            'AWARDED' => 'Encerrado (adm)',
            default => $status,
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
            ->where('stage', $this->pool->stage)
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->get();

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
        $nearestTickerMatches = $matches
            ->filter(fn (FootballMatch $m) => (int) ($m->matchday ?? 0) === 1)
            ->sortBy(fn (FootballMatch $m) => ($m->local_date ?? $m->utc_date))
            ->values();

        $statusLabels = [];
        $predictionStatuses = [];
        foreach ($matches as $match) {
            $statusLabels[$match->id] = $this->matchStatusLabel($match->status);
            $predictionStatuses[$match->id] = $this->predictionStatusLabel($match, $predictions->get($match->id));
        }

        $rankings = $this->pool->rankings()
            ->with('user:id,name,area')
            ->orderBy('position')
            ->get();

        $myRanking = $rankings->firstWhere('user_id', $userId);

        $totalMatches = $matches->count();
        $predictedCount = $predictions->count();

        return view('livewire.pools.poolshow', compact(
            'member', 'groupedMatches', 'predictions', 'statusLabels',
            'predictionStatuses', 'rankings', 'myRanking', 'totalMatches', 'predictedCount', 'nearestTickerMatches'
        ));
    }
}
