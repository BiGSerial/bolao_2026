<?php

namespace App\Support\Api;

use App\Models\FootballMatch;
use Illuminate\Support\Str;

class MatchDetailPayload
{
    private function __construct(private readonly FootballMatch $match) {}

    public static function fromModel(FootballMatch $match): array
    {
        $match->loadMissing(['homeTeam', 'awayTeam', 'detail', 'competition']);
        $instance = new self($match);

        $base = MatchPayload::fromModel($match);

        $base['score'] = [
            'home'      => $match->home_score_full_time,
            'away'      => $match->away_score_full_time,
            'home_half' => $match->home_score_half_time,
            'away_half' => $match->away_score_half_time,
        ];

        return array_merge($base, [
            'has_detail' => $match->detail !== null,
            'lineups'    => $instance->lineups(),
            'stats'      => $instance->statsRows(),
            'events'     => $instance->allEvents(),
        ]);
    }

    private function lineups(): array
    {
        $payload    = $this->match->detail?->payload ?? [];
        $apiLineups = (array) data_get($payload, '_api_football.lineups', []);

        return [
            'home' => [
                'formation' => (string) data_get($apiLineups, '0.formation', ''),
                'coach'     => (string) data_get($apiLineups, '0.coach.name', ''),
                'starters'  => $this->normalizedLineup('home'),
                'bench'     => $this->normalizedBench('home'),
            ],
            'away' => [
                'formation' => (string) data_get($apiLineups, '1.formation', ''),
                'coach'     => (string) data_get($apiLineups, '1.coach.name', ''),
                'starters'  => $this->normalizedLineup('away'),
                'bench'     => $this->normalizedBench('away'),
            ],
        ];
    }

    private function normalizedLineup(string $side): array
    {
        $payload = $this->match->detail?->payload ?? [];
        $items   = data_get($payload, "{$side}Team.lineup", []);

        if (empty($items)) {
            $apiLineups = (array) data_get($payload, '_api_football.lineups', []);
            $index      = $side === 'home' ? 0 : 1;
            $items      = (array) data_get($apiLineups, "{$index}.startXI", []);
        }

        $lineup = collect($items)->map(fn ($p) => [
            'name'     => (string) data_get($p, 'name', data_get($p, 'player.name', 'Jogador')),
            'number'   => data_get($p, 'shirtNumber', data_get($p, 'player.number')),
            'position' => (string) data_get($p, 'position', data_get($p, 'player.pos', '?')),
        ])->values()->all();

        return $this->applyLiveSubstitutionsToLineup($lineup, $side);
    }

    private function normalizedBench(string $side): array
    {
        $payload = $this->match->detail?->payload ?? [];
        $items   = data_get($payload, "{$side}Team.bench", []);

        if (empty($items)) {
            $apiLineups = (array) data_get($payload, '_api_football.lineups', []);
            $index      = $side === 'home' ? 0 : 1;
            $items      = (array) data_get($apiLineups, "{$index}.substitutes", []);
        }

        $bench = collect($items)->map(fn ($p) => [
            'name'     => (string) data_get($p, 'name', data_get($p, 'player.name', 'Reserva')),
            'number'   => data_get($p, 'shirtNumber', data_get($p, 'player.number')),
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

        return collect($bench)
            ->reject(function (array $player) use ($enteredNames): bool {
                $name = mb_strtolower(trim((string) ($player['name'] ?? '')));
                return $name !== '' && in_array($name, $enteredNames, true);
            })
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $lineup */
    private function applyLiveSubstitutionsToLineup(array $lineup, string $side): array
    {
        $subEvents = $this->substitutionEventsForSide($side);
        if ($subEvents === []) {
            return $lineup;
        }

        $normalize = static fn (?string $name): string => mb_strtolower(trim((string) $name));

        foreach ($subEvents as $subEvent) {
            $outNorm = $normalize((string) ($subEvent['out'] ?? ''));
            $inName  = (string) ($subEvent['in'] ?? '');

            foreach ($lineup as $idx => $player) {
                if ($normalize((string) ($player['name'] ?? '')) === $outNorm) {
                    $lineup[$idx]['sub_out']     = true;
                    $lineup[$idx]['replaced_by'] = $inName;
                    $lineup[$idx]['sub_minute']  = $subEvent['minute'] ?? null;

                    array_splice($lineup, $idx, 0, [[
                        'name'       => $inName,
                        'number'     => $this->findPlayerNumberByName($inName, $side),
                        'position'   => (string) ($player['position'] ?? '?'),
                        'sub_in'     => true,
                        'sub_minute' => $subEvent['minute'] ?? null,
                    ]]);
                    break;
                }
            }
        }

        return $lineup;
    }

    /** @return array<int, array{out:string,in:string,minute:int|null}> */
    private function substitutionEventsForSide(string $side): array
    {
        $events   = (array) data_get($this->match->detail?->payload, '_api_football.events', []);
        $teamName = $side === 'home'
            ? (string) ($this->match->homeTeam?->name ?? '')
            : (string) ($this->match->awayTeam?->name ?? '');

        $rows = [];
        foreach ($events as $event) {
            $type      = mb_strtolower((string) data_get($event, 'type', ''));
            $detail    = mb_strtolower((string) data_get($event, 'detail', ''));
            $eventTeam = (string) data_get($event, 'team.name', '');

            $isSub = str_contains($type, 'subst') || str_contains($detail, 'substitution');
            if (! $isSub || $eventTeam === '' || ! $this->teamNameMatches($eventTeam, $teamName)) {
                continue;
            }

            $outName = (string) data_get($event, 'player.name', '');
            $inName  = (string) data_get($event, 'assist.name', '');
            if ($outName === '' || $inName === '') {
                continue;
            }

            $minute = data_get($event, 'time.elapsed');
            $rows[] = [
                'out'    => $outName,
                'in'     => $inName,
                'minute' => is_numeric($minute) ? (int) $minute : null,
            ];
        }

        return $rows;
    }

    private function findPlayerNumberByName(string $name, string $side): int|string|null
    {
        $payload    = $this->match->detail?->payload ?? [];
        $apiLineups = (array) data_get($payload, '_api_football.lineups', []);
        $index      = $side === 'home' ? 0 : 1;
        $subs       = (array) data_get($apiLineups, "{$index}.substitutes", []);

        $normalized        = mb_strtolower(trim($name));
        $normalizedCompact = preg_replace('/[^\p{L}\p{N}]/u', '', $normalized) ?? $normalized;
        $nameTokens        = array_values(array_filter(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized)));
        $nameInitial       = $nameTokens !== [] ? mb_substr($nameTokens[0], 0, 1) : '';
        $nameLast          = $nameTokens !== [] ? end($nameTokens) : '';

        foreach ($subs as $player) {
            $pName    = mb_strtolower(trim((string) data_get($player, 'player.name', '')));
            $pCompact = preg_replace('/[^\p{L}\p{N}]/u', '', $pName) ?? $pName;

            if ($pName === $normalized || $pCompact === $normalizedCompact) {
                return data_get($player, 'player.number');
            }

            $pTokens  = array_values(array_filter(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $pName) ?? $pName)));
            $pInitial = $pTokens !== [] ? mb_substr($pTokens[0], 0, 1) : '';
            $pLast    = $pTokens !== [] ? end($pTokens) : '';

            if ($nameLast !== '' && $pLast !== '' && $nameLast === $pLast) {
                if ($nameInitial === '' || $pInitial === '' || $nameInitial === $pInitial) {
                    return data_get($player, 'player.number');
                }
            }
        }

        return null;
    }

    /** @return array<int, array<string, string>> */
    private function statsRows(): array
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

        return $this->fallbackStatsRows();
    }

    /** @param array<string, mixed> $payload */
    private function rowsFromLegacyStatistics(array $payload): array
    {
        $home = data_get($payload, 'homeTeam.statistics', []);
        $away = data_get($payload, 'awayTeam.statistics', []);
        if (! is_array($home) || ! is_array($away) || ($home === [] && $away === [])) {
            return [];
        }

        $map = [
            'shots'            => 'Chutes',
            'shots_on_goal'    => 'Chutes a gol',
            'ball_possession'  => 'Posse de bola',
            'passes'           => 'Passes',
            'passing_accuracy' => 'Precisão de passe',
            'fouls'            => 'Faltas',
            'yellow_cards'     => 'Amarelos',
            'red_cards'        => 'Vermelhos',
            'offsides'         => 'Impedimentos',
            'corner_kicks'     => 'Escanteios',
        ];

        $rows = [];
        $pctKeys = ['ball_possession', 'passing_accuracy'];
        foreach ($map as $key => $label) {
            $hv  = data_get($home, $key);
            $av  = data_get($away, $key);
            $pct = in_array($key, $pctKeys, true);
            $rows[] = [
                'label' => $label,
                'home'  => ($hv === null || $hv === '') ? '-' : ((string) $hv . ($pct ? '%' : '')),
                'away'  => ($av === null || $av === '') ? '-' : ((string) $av . ($pct ? '%' : '')),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $payload */
    private function rowsFromApiFootballStatistics(array $payload): array
    {
        $stats = data_get($payload, '_api_football.statistics', data_get($payload, 'statistics', []));
        if (! is_array($stats) || count($stats) < 2) {
            return [];
        }

        $toMap = fn (array $list): \Illuminate\Support\Collection => collect($list)
            ->mapWithKeys(fn ($item): array => ($t = trim((string) data_get($item, 'type', ''))) === '' ? [] : [$t => data_get($item, 'value')]);

        $homeStats = $toMap((array) data_get($stats, '0.statistics', []));
        $awayStats = $toMap((array) data_get($stats, '1.statistics', []));

        $types = $homeStats->keys()->merge($awayStats->keys())->unique()->values();
        if ($types->isEmpty()) {
            return [];
        }

        $preferredOrder = [
            'Shots on Goal', 'Shots off Goal', 'Total Shots', 'Blocked Shots',
            'Shots insidebox', 'Shots outsidebox', 'Fouls', 'Corner Kicks',
            'Offsides', 'Ball Possession', 'Yellow Cards', 'Red Cards',
            'Goalkeeper Saves', 'Total passes', 'Passes accurate', 'Passes %', 'expected_goals',
        ];

        $types = $types->sortBy(fn (string $t): int => ($i = array_search($t, $preferredOrder, true)) === false ? 999 : $i)->values();

        $rows = [];
        foreach ($types as $type) {
            $hv = $homeStats->get($type);
            $av = $awayStats->get($type);
            if (($hv === null || $hv === '') && ($av === null || $av === '')) {
                continue;
            }
            $rows[] = [
                'label' => $this->translateApiStatLabel($type),
                'home'  => $this->normalizeStatValue($hv),
                'away'  => $this->normalizeStatValue($av),
            ];
        }

        return $rows;
    }

    private function fallbackStatsRows(): array
    {
        return [
            ['label' => 'Gols (Final)', 'home' => (string) ($this->match->home_score_full_time ?? 0), 'away' => (string) ($this->match->away_score_full_time ?? 0)],
            ['label' => 'Gols (1º Tempo)', 'home' => (string) ($this->match->home_score_half_time ?? 0), 'away' => (string) ($this->match->away_score_half_time ?? 0)],
        ];
    }

    private function allEvents(): array
    {
        $events = (array) data_get($this->match->detail?->payload, '_api_football.events', []);
        if ($events === []) {
            return [];
        }

        $homeName = (string) ($this->match->homeTeam?->name ?? '');
        $result   = [];

        foreach ($events as $event) {
            $type        = mb_strtolower((string) data_get($event, 'type', ''));
            $detail      = (string) data_get($event, 'detail', '');
            $detailLower = mb_strtolower($detail);
            $teamName    = (string) data_get($event, 'team.name', '');
            $playerName  = (string) data_get($event, 'player.name', '');
            $assistName  = (string) data_get($event, 'assist.name', '');
            $minute      = data_get($event, 'time.elapsed');
            $extra       = data_get($event, 'time.extra');
            $isHome      = $this->teamNameMatches($teamName, $homeName);

            $mappedType = null;
            $subtype    = '';

            if ($type === 'goal') {
                if (str_contains($detailLower, 'disallow') || str_contains($detailLower, 'cancel')
                    || str_contains($detailLower, 'annul') || str_contains($detailLower, 'var')) {
                    continue;
                }
                $mappedType = 'goal';
                $subtype    = str_contains($detailLower, 'own') ? 'own_goal'
                    : (str_contains($detailLower, 'penalty') ? 'penalty' : 'normal');
            } elseif ($type === 'card') {
                $mappedType = 'card';
                $subtype    = str_contains($detailLower, 'red') ? 'red'
                    : (str_contains($detailLower, 'second') ? 'yellow_red' : 'yellow');
            } elseif (str_contains($type, 'subst')) {
                $mappedType = 'substitution';
                $subtype    = 'normal';
            }

            if ($mappedType === null) {
                continue;
            }

            $result[] = [
                'type'         => $mappedType,
                'subtype'      => $subtype,
                'minute'       => is_numeric($minute) ? (int) $minute : null,
                'extra_minute' => is_numeric($extra) ? (int) $extra : null,
                'is_home'      => $isHome,
                'team'         => $teamName,
                'player'       => $playerName !== '' ? $playerName : 'Jogador',
                'secondary'    => $assistName,
            ];
        }

        usort($result, fn (array $a, array $b): int =>
            (($b['minute'] ?? 0) * 100 + ($b['extra_minute'] ?? 0)) - (($a['minute'] ?? 0) * 100 + ($a['extra_minute'] ?? 0))
        );

        return $result;
    }

    private function normalizeStatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }
        return trim((string) $value) !== '' ? (string) $value : '-';
    }

    private function translateApiStatLabel(string $type): string
    {
        return [
            'Shots on Goal'    => 'Chutes no gol',
            'Shots off Goal'   => 'Chutes para fora',
            'Total Shots'      => 'Total de chutes',
            'Blocked Shots'    => 'Chutes bloqueados',
            'Shots insidebox'  => 'Chutes na área',
            'Shots outsidebox' => 'Chutes fora da área',
            'Fouls'            => 'Faltas',
            'Corner Kicks'     => 'Escanteios',
            'Offsides'         => 'Impedimentos',
            'Ball Possession'  => 'Posse de bola',
            'Yellow Cards'     => 'Amarelos',
            'Red Cards'        => 'Vermelhos',
            'Goalkeeper Saves' => 'Defesas do goleiro',
            'Total passes'     => 'Passes totais',
            'Passes accurate'  => 'Passes certos',
            'Passes %'         => 'Precisão de passe',
            'expected_goals'   => 'xG',
        ][$type] ?? $type;
    }

    private function teamNameMatches(string $left, string $right): bool
    {
        $a = $this->teamAliasKey($left);
        $b = $this->teamAliasKey($right);
        if ($a !== null && $b !== null) {
            return $a === $b;
        }
        return $this->normalizeTeam($left) === $this->normalizeTeam($right);
    }

    private function normalizeTeam(string $value): string
    {
        $value = mb_strtoupper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9 ]/u', '', $value) ?? '';
        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function teamAliasKey(string $value): ?string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', $this->normalizeTeam($value)) ?? '';
        if ($compact === '') {
            return null;
        }

        $aliases = [
            'ATLETICO_MG'   => ['ATLETICOMG', 'ATLETICOMINEIRO', 'CAMINEIRO', 'MINEIRO'],
            'ATLETICO_PR'   => ['ATLETICOPARANAENSE', 'CAPARANAENSE', 'CAP', 'ATHLETICOPR', 'ATHLETICOPARANAENSE'],
            'BAHIA'         => ['BAHIA', 'ECBAHIA'],
            'BOTAFOGO'      => ['BOTAFOGO', 'BOTAFOGOFR'],
            'CHAPECOENSE'   => ['CHAPECOENSE', 'CHAPECOENSESC', 'CHAPECOENSEAF'],
            'CORINTHIANS'   => ['CORINTHIANS', 'SCCORINTHIANSPAULISTA', 'CORINTHIANSPAULISTA'],
            'CORITIBA'      => ['CORITIBA', 'CORITIBAFBC'],
            'CRUZEIRO'      => ['CRUZEIRO', 'CRUZEIROEC'],
            'FLAMENGO'      => ['FLAMENGO', 'CRFLAMENGO'],
            'FLUMINENSE'    => ['FLUMINENSE', 'FLUMINENSEFC'],
            'GREMIO'        => ['GREMIO', 'GREMIOFBPA'],
            'INTERNACIONAL' => ['INTERNACIONAL', 'SCINTERNACIONAL'],
            'MIRASSOL'      => ['MIRASSOL', 'MIRASSOLFC'],
            'PALMEIRAS'     => ['PALMEIRAS', 'SEPALMEIRAS'],
            'RB_BRAGANTINO' => ['RBBRAGANTINO', 'REDBULLBRAGANTINO', 'BRAGANTINO'],
            'REMO'          => ['REMO', 'CLUBEDOREMO'],
            'SANTOS'        => ['SANTOS', 'SANTOSFC'],
            'SAO_PAULO'     => ['SAOPAULO', 'SAOPAULOFC'],
            'VASCO'         => ['VASCODAGAMA', 'CRVASCODAGAMA', 'VASCO'],
            'VITORIA'       => ['VITORIA', 'ECVITORIA'],
        ];

        foreach ($aliases as $key => $group) {
            foreach ($group as $alias) {
                if ($compact === $alias || str_contains($compact, $alias) || str_contains($alias, $compact)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
