<?php

namespace App\Livewire\Admin;

use App\Jobs\CalculatePredictionsForMatchJob;
use App\Jobs\RecalculatePoolRankingsJob;
use App\Models\FootballMatch;
use App\Models\Pool;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ManualMatchCorrection extends Component
{
    public string $search = '';
    public string $filterStatus = 'FINISHED';

    public array $editing = [];

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function startEdit(int $matchId): void
    {
        $this->assertAdmin();

        $match = FootballMatch::findOrFail($matchId);
        $this->editing[$matchId] = [
            'home' => $match->home_score_full_time ?? 0,
            'away' => $match->away_score_full_time ?? 0,
            'status' => $match->status,
        ];
    }

    public function cancelEdit(int $matchId): void
    {
        $this->assertAdmin();

        unset($this->editing[$matchId]);
    }

    private const ALLOWED_STATUSES = [
        'TIMED', 'SCHEDULED', 'IN_PLAY', 'PAUSED',
        'FINISHED', 'POSTPONED', 'CANCELLED', 'SUSPENDED', 'AWARDED',
    ];

    public function saveCorrection(int $matchId): void
    {
        $this->assertAdmin();

        $data = $this->editing[$matchId] ?? null;
        if (! $data) return;

        $h = max(0, min(30, (int) $data['home']));
        $a = max(0, min(30, (int) $data['away']));
        $status = $data['status'] ?? '';

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            session()->flash('error', 'Status de jogo inválido.');
            return;
        }

        $match = FootballMatch::findOrFail($matchId);
        $match->update([
            'home_score_full_time' => $h,
            'away_score_full_time' => $a,
            'status'               => $status,
        ]);

        if ($status === 'FINISHED') {
            CalculatePredictionsForMatchJob::dispatch($match->id);
            Pool::query()->pluck('id')->each(fn (int $id) => RecalculatePoolRankingsJob::dispatch($id));
        }

        unset($this->editing[$matchId]);
        session()->flash('status', "Jogo #{$matchId} corrigido e palpites recalculados.");
    }

    public function render()
    {
        $this->assertAdmin();

        $matches = FootballMatch::query()
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) =>
                $q->whereHas('homeTeam', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                  ->orWhereHas('awayTeam', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->orderBy('utc_date')
            ->paginate(20);

        return view('livewire.admin.manualmatchcorrection', compact('matches'));
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }
}
