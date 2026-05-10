<?php

namespace App\Livewire\Matches;

use App\Models\FootballMatch;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class MatchShow extends Component
{
    public FootballMatch $match;

    public function mount(FootballMatch $match): void
    {
        $this->match = $match->load(['homeTeam', 'awayTeam', 'detail', 'competition']);
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
        $rows = $this->rowsFromLegacyStatistics($payload);
        if ($rows !== []) {
            return $rows;
        }

        $rows = $this->rowsFromApiFootballStatistics($payload);
        if ($rows !== []) {
            return $rows;
        }

        return $this->fallbackStatsRowsFromScorePayload($payload);
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

    public function resolveLiveMinute(): ?int
    {
        $match = $this->match;

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

        $elapsed = $match->utc_date->diffInMinutes(now()->utc(), false);
        if ($elapsed <= 0) {
            return 1;
        }

        return max(1, min(130, $elapsed + 1));
    }

    public function render()
    {
        return view('livewire.matches.match-show');
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

        return collect($items)->map(fn ($p) => [
            'name' => (string) data_get($p, 'name', data_get($p, 'player.name', 'Jogador')),
            'number' => data_get($p, 'shirtNumber', data_get($p, 'player.number')),
            'position' => (string) data_get($p, 'position', data_get($p, 'player.pos', '?')),
        ])->values()->all();
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
    private function rowsFromLegacyStatistics(array $payload): array
    {
        $home = data_get($payload, 'homeTeam.statistics', []);
        $away = data_get($payload, 'awayTeam.statistics', []);
        if (! is_array($home) || ! is_array($away) || ($home === [] && $away === [])) {
            return [];
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

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, string>>
     */
    private function rowsFromApiFootballStatistics(array $payload): array
    {
        $stats = data_get($payload, '_api_football.statistics', data_get($payload, 'statistics', []));
        if (! is_array($stats) || count($stats) < 2) {
            return [];
        }

        $homeStats = collect((array) data_get($stats, '0.statistics', []))
            ->mapWithKeys(function ($item): array {
                $type = trim((string) data_get($item, 'type', ''));
                if ($type === '') {
                    return [];
                }
                return [$type => data_get($item, 'value')];
            });

        $awayStats = collect((array) data_get($stats, '1.statistics', []))
            ->mapWithKeys(function ($item): array {
                $type = trim((string) data_get($item, 'type', ''));
                if ($type === '') {
                    return [];
                }
                return [$type => data_get($item, 'value')];
            });

        $types = $homeStats->keys()->merge($awayStats->keys())->unique()->values();
        if ($types->isEmpty()) {
            return [];
        }

        $preferredOrder = [
            'Shots on Goal',
            'Shots off Goal',
            'Total Shots',
            'Blocked Shots',
            'Shots insidebox',
            'Shots outsidebox',
            'Fouls',
            'Corner Kicks',
            'Offsides',
            'Ball Possession',
            'Yellow Cards',
            'Red Cards',
            'Goalkeeper Saves',
            'Total passes',
            'Passes accurate',
            'Passes %',
            'expected_goals',
        ];

        $types = $types->sortBy(function (string $type) use ($preferredOrder): int {
            $idx = array_search($type, $preferredOrder, true);
            return $idx === false ? 999 : $idx;
        })->values();

        $rows = [];
        foreach ($types as $type) {
            $homeValue = $homeStats->get($type);
            $awayValue = $awayStats->get($type);

            if (($homeValue === null || $homeValue === '') && ($awayValue === null || $awayValue === '')) {
                continue;
            }

            $rows[] = [
                'label' => $this->translateApiStatLabel($type),
                'home' => $this->normalizeApiStatValue($homeValue),
                'away' => $this->normalizeApiStatValue($awayValue),
            ];
        }

        return $rows;
    }

    private function normalizeApiStatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value) !== '' ? (string) $value : '-';
    }

    private function translateApiStatLabel(string $type): string
    {
        $map = [
            'Shots on Goal' => 'Chutes no gol',
            'Shots off Goal' => 'Chutes para fora',
            'Total Shots' => 'Total de chutes',
            'Blocked Shots' => 'Chutes bloqueados',
            'Shots insidebox' => 'Chutes na área',
            'Shots outsidebox' => 'Chutes fora da área',
            'Fouls' => 'Faltas',
            'Corner Kicks' => 'Escanteios',
            'Offsides' => 'Impedimentos',
            'Ball Possession' => 'Posse de bola',
            'Yellow Cards' => 'Cartões amarelos',
            'Red Cards' => 'Cartões vermelhos',
            'Goalkeeper Saves' => 'Defesas do goleiro',
            'Total passes' => 'Passes totais',
            'Passes accurate' => 'Passes certos',
            'Passes %' => 'Precisão de passe',
            'expected_goals' => 'xG',
        ];

        return $map[$type] ?? $type;
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
