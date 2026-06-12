<?php

namespace App\Livewire\Matches;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Services\Pools\LivePoolRankingService;
use App\Support\Teams\TeamNameMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MatchShow extends Component
{
    public FootballMatch $match;

    public ?int $sidebarPoolId = null;

    public function mount(FootballMatch $match): void
    {
        $this->match = $match->load(['homeTeam', 'awayTeam', 'detail', 'competition']);
        $this->sidebarPoolId = $this->resolveSidebarPoolId();
    }

    protected function getListeners(): array
    {
        $listeners = [
            'echo:matches,MatchUpdated' => 'refreshLiveData',
            'echo:matches,MatchDetailUpdated' => 'refreshLiveData',
        ];

        if ($this->sidebarPoolId) {
            $listeners["echo-private:pool.{$this->sidebarPoolId},RankingUpdated"] = 'refreshLiveData';
        }

        return $listeners;
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
                $isHome = $this->teamNameMatches($teamName, $homeName);
                $isAway = $this->teamNameMatches($teamName, $awayName);
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
            ->sortByDesc(function (array $e): int {
                $minute = (int) ($e['minute'] ?? 0);
                $extra = (int) ($e['extra_minute'] ?? 0);

                return ($minute * 100) + $extra;
            })
            ->values()
            ->all();
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

    public function refreshLiveData(array $event = []): void
    {
        $eventMatchId = (int) data_get($event, 'match_id', data_get($event, 'id', 0));
        if ($eventMatchId !== 0 && $eventMatchId !== (int) $this->match->id) {
            return;
        }

        $this->match->refresh()->load(['homeTeam', 'awayTeam', 'detail']);
    }

    private function resolveSidebarPoolId(): ?int
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return null;
        }

        $poolId = PoolMember::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('pool', fn ($q) => $q
                ->where('competition_id', (int) $this->match->competition_id)
                ->where('competition_season_id', (int) $this->match->competition_season_id)
            )
            ->latest('id')
            ->value('pool_id');

        return is_numeric($poolId) ? (int) $poolId : null;
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
        return $this->resolveLiveMinuteForMatch($this->match);
    }

    public function render()
    {
        return view('livewire.matches.match-show');
    }

    /**
     * @return array{
     *   pool:?Pool,
     *   ranking:?object,
     *   top_rankings:Collection,
     *   member_count:int
     * }
     */
    public function sidebarPoolContext(): array
    {
        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return [
                'pool' => null,
                'ranking' => null,
                'top_rankings' => collect(),
                'member_count' => 0,
            ];
        }

        $pool = PoolMember::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('pool', fn ($q) => $q
                ->where('competition_id', (int) $this->match->competition_id)
                ->where('competition_season_id', (int) $this->match->competition_season_id)
            )
            ->with(['pool:id,name,slug,status,competition_id,competition_season_id'])
            ->latest('id')
            ->first()?->pool;

        if (! $pool) {
            return [
                'pool' => null,
                'ranking' => null,
                'top_rankings' => collect(),
                'member_count' => 0,
            ];
        }

        $liveRankingRows = app(LivePoolRankingService::class)->build($pool);
        $ranking = $liveRankingRows->firstWhere('user_id', $userId);
        $topRankings = $liveRankingRows->take(5)->values();

        if ($ranking && (int) ($ranking->position ?? 0) > 5) {
            $topFour = $liveRankingRows->take(4)->values();
            $myRow = $liveRankingRows->firstWhere('user_id', $userId);
            if ($myRow) {
                $topRankings = $topFour->concat(collect([$myRow]))->values();
            }
        }

        $memberCount = PoolMember::query()
            ->where('pool_id', (int) $pool->id)
            ->where('status', 'active')
            ->count();

        return [
            'pool' => $pool,
            'ranking' => $ranking,
            'top_rankings' => $topRankings,
            'member_count' => $memberCount,
        ];
    }

    /**
     * @return array{live:Collection,liveMinutes:array<int,int|null>,liveMatchStats:array<int,array<string,int|null>>}
     */
    public function sidebarLiveContext(): array
    {
        $live = FootballMatch::query()
            ->where('competition_id', (int) $this->match->competition_id)
            ->where('competition_season_id', (int) $this->match->competition_season_id)
            ->whereIn('status', ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
            ->with(['homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->limit(3)
            ->get();

        $liveMinutes = [];
        $liveMatchStats = [];

        foreach ($live as $liveMatch) {
            $liveMinutes[(int) $liveMatch->id] = $this->resolveLiveMinuteForMatch($liveMatch);
            $liveMatchStats[(int) $liveMatch->id] = $this->extractLiveStatsForMatch($liveMatch);
        }

        return [
            'live' => $live,
            'liveMinutes' => $liveMinutes,
            'liveMatchStats' => $liveMatchStats,
        ];
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

        $bench = collect($items)->map(fn ($p) => [
            'name' => (string) data_get($p, 'name', data_get($p, 'player.name', 'Reserva')),
            'number' => data_get($p, 'shirtNumber', data_get($p, 'player.number')),
            'position' => (string) data_get($p, 'position', data_get($p, 'player.pos', '?')),
        ])->values()->all();

        $subEvents = $this->substitutionEventsForSide($side);
        if ($subEvents === []) {
            return $bench;
        }

        $enteredNames = collect($subEvents)
            ->map(fn (array $e) => mb_strtolower(trim((string) ($e['in'] ?? ''))))
            ->filter()
            ->values()
            ->all();

        if ($enteredNames === []) {
            return $bench;
        }

        return collect($bench)
            ->reject(function (array $player) use ($enteredNames): bool {
                $name = mb_strtolower(trim((string) ($player['name'] ?? '')));

                return $name !== '' && in_array($name, $enteredNames, true);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineup
     * @return array<int, array<string, mixed>>
     */
    private function applyLiveSubstitutionsToLineup(array $lineup, string $side): array
    {
        $subEvents = $this->substitutionEventsForSide($side);
        if ($subEvents === []) {
            return $lineup;
        }

        $normalize = static fn (?string $name): string => mb_strtolower(trim((string) $name));

        foreach ($subEvents as $subEvent) {
            $outName = (string) ($subEvent['out'] ?? '');
            $inName = (string) ($subEvent['in'] ?? '');
            $outNorm = $normalize($outName);
            foreach ($lineup as $idx => $player) {
                if ($normalize((string) ($player['name'] ?? '')) === $outNorm) {
                    $lineup[$idx]['sub_out'] = true;
                    $lineup[$idx]['replaced_by'] = $inName;
                    $lineup[$idx]['sub_minute'] = $subEvent['minute'] ?? null;

                    array_splice($lineup, $idx, 0, [[
                        'name' => $inName,
                        'number' => $this->findPlayerNumberByName($inName, $side),
                        'position' => (string) ($player['position'] ?? '?'),
                        'sub_in' => true,
                        'sub_minute' => $subEvent['minute'] ?? null,
                    ]]);
                    break;
                }
            }
        }

        return $lineup;
    }

    /**
     * @return array<int, array{out:string,in:string,minute:int|null}>
     */
    private function substitutionEventsForSide(string $side): array
    {
        $events = (array) data_get($this->match->detail?->payload, '_api_football.events', []);
        if ($events === []) {
            return [];
        }

        $teamName = $side === 'home'
            ? (string) ($this->match->homeTeam?->name ?? '')
            : (string) ($this->match->awayTeam?->name ?? '');

        $rows = [];
        foreach ($events as $event) {
            $type = mb_strtolower((string) data_get($event, 'type', ''));
            $detail = mb_strtolower((string) data_get($event, 'detail', ''));
            $eventTeam = (string) data_get($event, 'team.name', '');

            $isSub = str_contains($type, 'subst') || str_contains($detail, 'substitution');
            if (! $isSub || $eventTeam === '' || ! $this->teamNameMatches($eventTeam, $teamName)) {
                continue;
            }

            $outName = (string) data_get($event, 'player.name', '');
            $inName = (string) data_get($event, 'assist.name', '');
            if ($outName === '' || $inName === '') {
                continue;
            }

            $minute = data_get($event, 'time.elapsed');
            $rows[] = [
                'out' => $outName,
                'in' => $inName,
                'minute' => is_numeric($minute) ? (int) $minute : null,
            ];
        }

        return $rows;
    }

    private function findPlayerNumberByName(string $name, string $side): int|string|null
    {
        $payload = $this->match->detail?->payload ?? [];
        $apiLineups = (array) data_get($payload, '_api_football.lineups', []);
        $index = $side === 'home' ? 0 : 1;
        $subs = (array) data_get($apiLineups, "{$index}.substitutes", []);
        $normalized = mb_strtolower(trim($name));
        $normalizedCompact = preg_replace('/[^\p{L}\p{N}]/u', '', $normalized) ?? $normalized;
        $nameTokens = array_values(array_filter(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized)));
        $nameInitial = $nameTokens !== [] ? mb_substr($nameTokens[0], 0, 1) : '';
        $nameLast = $nameTokens !== [] ? end($nameTokens) : '';

        foreach ($subs as $player) {
            $pName = mb_strtolower(trim((string) data_get($player, 'player.name', '')));
            $pCompact = preg_replace('/[^\p{L}\p{N}]/u', '', $pName) ?? $pName;
            if ($pName === $normalized || $pCompact === $normalizedCompact) {
                return data_get($player, 'player.number');
            }

            $pTokens = array_values(array_filter(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $pName) ?? $pName)));
            $pInitial = $pTokens !== [] ? mb_substr($pTokens[0], 0, 1) : '';
            $pLast = $pTokens !== [] ? end($pTokens) : '';
            if ($nameLast !== '' && $pLast !== '' && $nameLast === $pLast) {
                if ($nameInitial === '' || $pInitial === '' || $nameInitial === $pInitial) {
                    return data_get($player, 'player.number');
                }
            }
        }

        return null;
    }

    private function teamNameMatches(string $left, string $right): bool
    {
        return TeamNameMatcher::matches($left, $right);
    }

    private function resolveLiveMinuteForMatch(FootballMatch $match): ?int
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

        return max(1, min(130, $elapsed <= 0 ? 1 : $elapsed));
    }

    /**
     * @return array<string,int|null>
     */
    private function extractLiveStatsForMatch(FootballMatch $match): array
    {
        $payload = (array) ($match->raw_payload ?? []);

        $homeShots = data_get($payload, 'stats.shots.home');
        $awayShots = data_get($payload, 'stats.shots.away');
        if (! is_numeric($homeShots) || ! is_numeric($awayShots)) {
            $homeShots = data_get($payload, 'api_football_statistics.home.shots_total');
            $awayShots = data_get($payload, 'api_football_statistics.away.shots_total');
        }

        $homePoss = data_get($payload, 'stats.possession.home');
        $awayPoss = data_get($payload, 'stats.possession.away');
        if (! is_numeric($homePoss) || ! is_numeric($awayPoss)) {
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
     * @param  array<string, mixed>  $payload
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
     * @param  array<string, mixed>  $payload
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
     * @param  array<string, mixed>  $payload
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
