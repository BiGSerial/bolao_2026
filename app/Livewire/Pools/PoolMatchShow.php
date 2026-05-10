<?php

namespace App\Livewire\Pools;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PoolMatchShow extends Component
{
    public Pool $pool;
    public FootballMatch $match;

    public function mount(Pool $pool, FootballMatch $match): void
    {
        $this->pool = $pool;
        $this->match = $match->load(['homeTeam', 'awayTeam', 'detail', 'competition']);

        $this->assertMember();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function homeLineup(): array
    {
        return $this->normalizedLineup('home');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function awayLineup(): array
    {
        return $this->normalizedLineup('away');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function homeBench(): array
    {
        return $this->normalizedBench('home');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function awayBench(): array
    {
        return $this->normalizedBench('away');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bookings(): array
    {
        return data_get($this->match->detail?->payload, 'bookings', []);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function statsRows(): array
    {
        $payload = $this->match->detail?->payload ?? [];
        $home = data_get($payload, 'homeTeam.statistics', []);
        $away = data_get($payload, 'awayTeam.statistics', []);

        // Fallback para competições em que a API não envia o bloco homeTeam/awayTeam.statistics.
        if (empty($home) && empty($away)) {
            return $this->fallbackStatsRowsFromScorePayload($payload);
        }

        $map = [
            'shots' => 'Chutes',
            'shots_on_goal' => 'Chutes a gol',
            'ball_possession' => 'Posse de bola',
            'passes' => 'Passes',
            'passing_accuracy' => 'Precisão de passe',
            'fouls' => 'Faltas',
            'yellow_cards' => 'Cartões amarelos',
            'red_cards' => 'Cartões vermelhos',
            'offsides' => 'Impedimentos',
            'corner_kicks' => 'Escanteios',
        ];

        $rows = [];
        foreach ($map as $key => $label) {
            $rows[] = [
                'label' => $label,
                'home' => $this->formatStatValue(data_get($home, $key), $key),
                'away' => $this->formatStatValue(data_get($away, $key), $key),
            ];
        }

        return $rows;
    }

    #[On('echo:matches,MatchUpdated')]
    #[On('echo:matches,MatchDetailUpdated')]
    public function refreshLiveData(array $event = []): void
    {
        $eventMatchId = (int) data_get($event, 'match_id', data_get($event, 'id', 0));
        if ($eventMatchId !== 0 && $eventMatchId !== (int) $this->match->id) {
            return;
        }

        $this->match->refresh()->load(['homeTeam', 'awayTeam', 'detail']);
    }

    public function hasMatchDetail(): bool
    {
        return $this->match->detail !== null;
    }

    public function statsUnavailableMessage(): string
    {
        if (! $this->hasMatchDetail()) {
            return 'Os dados serão carregados durante e após a partida.';
        }

        $code = strtoupper((string) ($this->match->competition?->code ?? ''));
        if ($code === 'BSA') {
            return 'A API não disponibilizou estatísticas detalhadas para esta partida do Brasileirão.';
        }

        return 'O provedor não retornou estatísticas detalhadas para esta partida.';
    }

    public function render()
    {
        return view('livewire.pools.pool-match-show');
    }

    private function assertMember(): PoolMember
    {
        $isAdmin = (bool) Auth::user()?->is_admin;
        abort_if(! $isAdmin && $this->pool->status !== 'active', 403);

        $member = $this->pool->members()->where('user_id', Auth::id())->first();
        abort_if(! $member, 403);
        return $member;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedLineup(string $side): array
    {
        $items = data_get($this->match->detail?->payload, "{$side}Team.lineup", []);

        return collect($items)->map(fn ($p) => [
            'name' => (string) data_get($p, 'name', 'Jogador'),
            'number' => data_get($p, 'shirtNumber'),
            'position' => (string) data_get($p, 'position', '?'),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedBench(string $side): array
    {
        $items = data_get($this->match->detail?->payload, "{$side}Team.bench", []);

        return collect($items)->map(fn ($p) => [
            'name' => (string) data_get($p, 'name', 'Reserva'),
            'number' => data_get($p, 'shirtNumber'),
            'position' => (string) data_get($p, 'position', '?'),
        ])->values()->all();
    }

    private function formatStatValue(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (in_array($key, ['ball_possession', 'passing_accuracy'], true)) {
            return ((string) $value).'%';
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, string>>
     */
    private function fallbackStatsRowsFromScorePayload(array $payload): array
    {
        $fullHome = (int) data_get($payload, 'score.fullTime.home', $this->match->home_score_full_time ?? 0);
        $fullAway = (int) data_get($payload, 'score.fullTime.away', $this->match->away_score_full_time ?? 0);
        $halfHome = (int) data_get($payload, 'score.halfTime.home', $this->match->home_score_half_time ?? 0);
        $halfAway = (int) data_get($payload, 'score.halfTime.away', $this->match->away_score_half_time ?? 0);

        $bookings = (array) data_get($payload, 'bookings', []);
        $homeName = (string) ($this->match->homeTeam?->name ?? '');
        $awayName = (string) ($this->match->awayTeam?->name ?? '');
        $homeCards = 0;
        $awayCards = 0;

        foreach ($bookings as $booking) {
            $teamName = (string) data_get($booking, 'team.name', '');
            if ($homeName !== '' && $teamName === $homeName) {
                $homeCards++;
            } elseif ($awayName !== '' && $teamName === $awayName) {
                $awayCards++;
            }
        }

        return [
            ['label' => 'Gols (Final)', 'home' => (string) $fullHome, 'away' => (string) $fullAway],
            ['label' => 'Gols (1º Tempo)', 'home' => (string) $halfHome, 'away' => (string) $halfAway],
            ['label' => 'Cartões', 'home' => (string) $homeCards, 'away' => (string) $awayCards],
        ];
    }

}
