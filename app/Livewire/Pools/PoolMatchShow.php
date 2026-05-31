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
     * @return array<int, array<string, mixed>>
     */
    public function goalEvents(): array
    {
        $events = (array) data_get($this->match->detail?->payload, '_api_football.events', []);
        $homeName = (string) ($this->match->homeTeam?->name ?? '');
        $awayName = (string) ($this->match->awayTeam?->name ?? '');

        return collect($events)
            ->filter(function ($event): bool {
                $type = strtolower((string) data_get($event, 'type', ''));
                $detail = strtolower((string) data_get($event, 'detail', ''));

                return $type === 'goal'
                    || str_contains($detail, 'goal')
                    || str_contains($detail, 'penalty')
                    || str_contains($detail, 'own goal');
            })
            ->map(function ($event) use ($homeName, $awayName): array {
                $teamName = (string) data_get($event, 'team.name', '');
                $playerName = (string) data_get($event, 'player.name', '');
                $assistName = (string) data_get($event, 'assist.name', '');
                $detail = (string) data_get($event, 'detail', '');
                $minute = data_get($event, 'time.elapsed');
                $extra = data_get($event, 'time.extra');
                $isHome = $teamName !== '' && $teamName === $homeName;
                $isAway = $teamName !== '' && $teamName === $awayName;
                $detailLower = mb_strtolower($detail);
                $isDisallowed = str_contains($detailLower, 'disallow')
                    || str_contains($detailLower, 'cancel')
                    || str_contains($detailLower, 'annul')
                    || str_contains($detailLower, 'var');

                return [
                    'team_name' => $teamName,
                    'player_name' => $playerName !== '' ? $playerName : 'Jogador não identificado',
                    'assist_name' => $assistName,
                    'detail' => $detail,
                    'minute' => is_numeric($minute) ? (int) $minute : null,
                    'extra_minute' => is_numeric($extra) ? (int) $extra : null,
                    'is_home' => $isHome,
                    'is_away' => $isAway,
                    'is_disallowed' => $isDisallowed,
                ];
            })
            ->sortBy([
                fn (array $e) => (int) ($e['minute'] ?? 999),
                fn (array $e) => (int) ($e['extra_minute'] ?? 0),
            ])
            ->values()
            ->all();
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

    public function resolveLiveMinute(): ?int
    {
        if (! in_array($this->match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true)) {
            return null;
        }

        $minute = data_get($this->match->raw_payload, 'minute');
        if (! is_numeric($minute)) {
            $minute = data_get($this->match->raw_payload, 'api_football_status.elapsed');
        }
        if (is_numeric($minute)) {
            return max(1, min(130, (int) $minute));
        }

        $trackedSeconds = (int) ($this->match->live_clock_accumulated_seconds ?? 0);
        if (in_array($this->match->status, ['IN_PLAY', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true) && $this->match->live_clock_anchor_at) {
            $trackedSeconds += max(0, now()->getTimestamp() - $this->match->live_clock_anchor_at->getTimestamp());
        }

        if ($trackedSeconds > 0) {
            return max(1, min(130, (int) floor($trackedSeconds / 60)));
        }

        $kickoff = $this->match->kickoffAtBrazil();
        if (! $kickoff) {
            return null;
        }

        $elapsed = $kickoff->diffInMinutes(now('America/Sao_Paulo'), false);

        return max(1, min(130, $elapsed <= 0 ? 1 : $elapsed));
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
        $payload = $this->match->detail?->payload ?? [];
        $items = data_get($payload, "{$side}Team.lineup", []);
        if (empty($items)) {
            $apiLineups = (array) data_get($payload, '_api_football.lineups', []);
            $index = $side === 'home' ? 0 : 1;
            $items = (array) data_get($apiLineups, "{$index}.startXI", []);
        }

        $lineup = collect($items)->map(fn ($p) => [
            'name' => (string) data_get($p, 'name', data_get($p, 'player.name', 'Jogador')),
            'number' => data_get($p, 'shirtNumber', data_get($p, 'player.number')),
            'position' => (string) data_get($p, 'position', data_get($p, 'player.pos', '?')),
        ])->values()->all();

        return $this->applyLiveSubstitutionsToLineup($lineup, $side);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedBench(string $side): array
    {
        $payload = $this->match->detail?->payload ?? [];
        $items = data_get($payload, "{$side}Team.bench", []);
        if (empty($items)) {
            $apiLineups = (array) data_get($payload, '_api_football.lineups', []);
            $index = $side === 'home' ? 0 : 1;
            $items = (array) data_get($apiLineups, "{$index}.substitutes", []);
        }

        return collect($items)->map(fn ($p) => [
            'name' => (string) data_get($p, 'name', data_get($p, 'player.name', 'Reserva')),
            'number' => data_get($p, 'shirtNumber', data_get($p, 'player.number')),
            'position' => (string) data_get($p, 'position', data_get($p, 'player.pos', '?')),
        ])->values()->all();
    }

    /**
     * @param array<int, array<string, mixed>> $lineup
     * @return array<int, array<string, mixed>>
     */
    private function applyLiveSubstitutionsToLineup(array $lineup, string $side): array
    {
        $events = (array) data_get($this->match->detail?->payload, '_api_football.events', []);
        if ($events === []) {
            return $lineup;
        }

        $teamName = $side === 'home'
            ? (string) ($this->match->homeTeam?->name ?? '')
            : (string) ($this->match->awayTeam?->name ?? '');

        $normalize = static fn (?string $name): string => mb_strtolower(trim((string) $name));

        foreach ($events as $event) {
            $type = mb_strtolower((string) data_get($event, 'type', ''));
            $detail = mb_strtolower((string) data_get($event, 'detail', ''));
            $eventTeam = (string) data_get($event, 'team.name', '');

            $isSub = str_contains($type, 'subst') || str_contains($detail, 'substitution');
            if (! $isSub || $eventTeam === '' || $eventTeam !== $teamName) {
                continue;
            }

            $outName = (string) data_get($event, 'player.name', '');
            $inName = (string) data_get($event, 'assist.name', '');
            if ($outName === '' || $inName === '') {
                continue;
            }

            $outNorm = $normalize($outName);
            foreach ($lineup as $idx => $player) {
                if ($normalize((string) ($player['name'] ?? '')) === $outNorm) {
                    $lineup[$idx]['name'] = $inName;
                    $lineup[$idx]['sub_in'] = true;
                    break;
                }
            }
        }

        return $lineup;
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
        $fullHome = (int) ($this->match->home_score_full_time ?? data_get($payload, 'score.fullTime.home', 0));
        $fullAway = (int) ($this->match->away_score_full_time ?? data_get($payload, 'score.fullTime.away', 0));
        $halfHome = (int) ($this->match->home_score_half_time ?? data_get($payload, 'score.halfTime.home', 0));
        $halfAway = (int) ($this->match->away_score_half_time ?? data_get($payload, 'score.halfTime.away', 0));

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
