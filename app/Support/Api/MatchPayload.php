<?php

namespace App\Support\Api;

use App\Models\FootballMatch;
use Illuminate\Support\Str;

class MatchPayload
{
    public static function fromModel(FootballMatch $match): array
    {
        $goalEvents = self::goalEvents($match);

        return [
            'id' => $match->id,
            'status' => (string) $match->status,
            'utc_date' => optional($match->utc_date)?->toIso8601String(),
            'local_date' => optional($match->kickoffAtBrazil())?->toIso8601String(),
            'stage' => $match->stage,
            'matchday' => $match->matchday,
            'competition' => [
                'id' => $match->competition?->id,
                'code' => $match->competition?->code,
                'name' => $match->competition?->name,
            ],
            'home_team' => [
                'id' => $match->homeTeam?->id,
                'name' => $match->homeTeam?->localized_name,
                'short_name' => $match->homeTeam?->canonical_name_br ?: $match->homeTeam?->short_name,
                'tla' => $match->homeTeam?->tla,
                'crest' => $match->homeTeam?->crest,
            ],
            'away_team' => [
                'id' => $match->awayTeam?->id,
                'name' => $match->awayTeam?->localized_name,
                'short_name' => $match->awayTeam?->canonical_name_br ?: $match->awayTeam?->short_name,
                'tla' => $match->awayTeam?->tla,
                'crest' => $match->awayTeam?->crest,
            ],
            'score' => [
                'home' => $match->home_score_full_time,
                'away' => $match->away_score_full_time,
            ],
            'live_clock' => self::liveClock($match),
            'goals' => [
                'home' => self::goalScorersBySide($goalEvents, true),
                'away' => self::goalScorersBySide($goalEvents, false),
            ],
        ];
    }

    private static function liveClock(FootballMatch $match): array
    {
        $payload = (array) ($match->raw_payload ?? []);

        $minute = data_get($payload, 'minute');
        if (! is_numeric($minute)) {
            $minute = data_get($payload, 'api_football_status.elapsed');
        }
        $extra = data_get($payload, 'injury_time');
        if (! is_numeric($extra)) {
            $extra = data_get($payload, 'api_football_status.extra');
        }

        return [
            'minute' => is_numeric($minute) ? (int) $minute : null,
            'extra_minute' => is_numeric($extra) ? (int) $extra : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function goalEvents(FootballMatch $match): array
    {
        $events = (array) data_get($match->detail?->payload, '_api_football.events', []);
        if ($events === []) {
            return [];
        }

        $homeName = (string) ($match->homeTeam?->name ?? '');
        $awayName = (string) ($match->awayTeam?->name ?? '');

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
                $detail = strtolower((string) data_get($event, 'detail', ''));
                $isDisallowed = str_contains($detail, 'disallow')
                    || str_contains($detail, 'cancel')
                    || str_contains($detail, 'annul')
                    || str_contains($detail, 'var');

                return [
                    'is_home' => self::teamNameMatches($teamName, $homeName),
                    'is_away' => self::teamNameMatches($teamName, $awayName),
                    'is_disallowed' => $isDisallowed,
                    'player_name' => (string) data_get($event, 'player.name', ''),
                    'player_number' => data_get($event, 'player.number'),
                    'minute' => is_numeric(data_get($event, 'time.elapsed')) ? (int) data_get($event, 'time.elapsed') : null,
                    'extra_minute' => is_numeric(data_get($event, 'time.extra')) ? (int) data_get($event, 'time.extra') : null,
                ];
            })
            ->filter(fn (array $event) => ! $event['is_disallowed'])
            ->values()
            ->all();
    }

    private static function teamNameMatches(string $left, string $right): bool
    {
        $a = self::teamAliasKey($left);
        $b = self::teamAliasKey($right);
        if ($a !== null && $b !== null) {
            return $a === $b;
        }

        return self::normalizeTeam($left) === self::normalizeTeam($right);
    }

    private static function normalizeTeam(string $value): string
    {
        $value = mb_strtoupper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9 ]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return $value;
    }

    private static function teamAliasKey(string $value): ?string
    {
        $compact = preg_replace('/[^A-Z0-9]/', '', self::normalizeTeam($value)) ?? '';
        if ($compact === '') {
            return null;
        }

        $aliases = [
            'ATLETICO_MG' => ['ATLETICOMG', 'ATLETICOMINEIRO', 'CAMINEIRO', 'MINEIRO'],
            'ATLETICO_PR' => ['ATLETICOPARANAENSE', 'CAPARANAENSE', 'CAP', 'ATHLETICOPR', 'ATHLETICOPARANAENSE'],
            'BAHIA' => ['BAHIA', 'ECBAHIA'],
            'BOTAFOGO' => ['BOTAFOGO', 'BOTAFOGOFR'],
            'CHAPECOENSE' => ['CHAPECOENSE', 'CHAPECOENSESC', 'CHAPECOENSEAF'],
            'CORINTHIANS' => ['CORINTHIANS', 'SCCORINTHIANSPAULISTA', 'CORINTHIANSPAULISTA'],
            'CORITIBA' => ['CORITIBA', 'CORITIBAFBC'],
            'CRUZEIRO' => ['CRUZEIRO', 'CRUZEIROEC'],
            'FLAMENGO' => ['FLAMENGO', 'CRFLAMENGO'],
            'FLUMINENSE' => ['FLUMINENSE', 'FLUMINENSEFC'],
            'GREMIO' => ['GREMIO', 'GREMIOFBPA'],
            'INTERNACIONAL' => ['INTERNACIONAL', 'SCINTERNACIONAL'],
            'MIRASSOL' => ['MIRASSOL', 'MIRASSOLFC'],
            'PALMEIRAS' => ['PALMEIRAS', 'SEPALMEIRAS'],
            'RB_BRAGANTINO' => ['RBBRAGANTINO', 'REDBULLBRAGANTINO', 'BRAGANTINO'],
            'REMO' => ['REMO', 'CLUBEDOREMO'],
            'SANTOS' => ['SANTOS', 'SANTOSFC'],
            'SAO_PAULO' => ['SAOPAULO', 'SAOPAULOFC'],
            'VASCO' => ['VASCODAGAMA', 'CRVASCODAGAMA', 'VASCO'],
            'VITORIA' => ['VITORIA', 'ECVITORIA'],
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

    /**
     * @param array<int, array<string, mixed>> $goalEvents
     * @return array<int, array<string, mixed>>
     */
    private static function goalScorersBySide(array $goalEvents, bool $home): array
    {
        return collect($goalEvents)
            ->filter(fn (array $event) => $home ? !empty($event['is_home']) : !empty($event['is_away']))
            ->map(fn (array $event): array => [
                'number' => is_numeric($event['player_number'] ?? null) ? (int) $event['player_number'] : null,
                'name' => (string) ($event['player_name'] ?? 'Jogador'),
                'minute' => $event['minute'],
                'extra_minute' => $event['extra_minute'],
            ])
            ->values()
            ->all();
    }
}
