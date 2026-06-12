<?php

namespace App\Services\Api\Connectors;

use App\Models\FootballMatch;
use App\Services\ApiFootball\ApiFootballClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ApiFootballConnector
{
    private int $requestCount = 0;
    private int $failureCount = 0;

    public function __construct(private readonly ApiFootballClient $client)
    {
    }

    public function resetMetrics(): void
    {
        $this->requestCount = 0;
        $this->failureCount = 0;
    }

    /**
     * @return array{requests:int,failures:int}
     */
    public function metrics(): array
    {
        return [
            'requests' => $this->requestCount,
            'failures' => $this->failureCount,
        ];
    }

    /**
     * @param Collection<int, FootballMatch> $matches
     * @return array<int, int>
     */
    public function resolveFixtureIds(Collection $matches, int $leagueId, int $season): array
    {
        $fixtureIdByMatch = [];
        $groupedByDate = $matches->groupBy(function (FootballMatch $m) {
            $raw = $m->getRawOriginal('utc_date');
            return $raw ? Carbon::parse($raw, 'UTC')->format('Y-m-d') : null;
        });

        foreach ($groupedByDate as $date => $dateMatches) {
            if (! is_string($date) || $date === '') {
                continue;
            }

            try {
                $this->requestCount++;
                $fixtures = $this->client->fixturesByDate($leagueId, $season, $date, 'UTC');
            } catch (\Throwable $e) {
                $this->failureCount++;
                throw $e;
            }
            $responseItems = (array) data_get($fixtures, 'response', []);

            // API-Football indexa jogos pela data local da liga, não UTC.
            // Jogos noturnos (ex: 23h30 BRT = 02h30 UTC do dia seguinte) ficam na data
            // anterior quando consultados com timezone=UTC. Tentamos o dia anterior
            // automaticamente quando a consulta principal retorna vazio.
            if ($responseItems === []) {
                $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
                try {
                    $this->requestCount++;
                    $prevFixtures = $this->client->fixturesByDate($leagueId, $season, $prevDate, 'UTC');
                    $responseItems = (array) data_get($prevFixtures, 'response', []);
                    if ($responseItems !== []) {
                        logger()->info('[af-resolver] fallback to previous day', [
                            'league_id'  => $leagueId,
                            'queried'    => $date,
                            'fallback'   => $prevDate,
                            'found'      => count($responseItems),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->failureCount++;
                }
            }

            $prevDateItems = null;
            foreach ($dateMatches as $match) {
                $id = $this->resolveFixtureIdForMatch($match, $responseItems);
                if ($id > 0) {
                    $fixtureIdByMatch[(int) $match->id] = $id;
                    continue;
                }

                // Jogo não encontrado na data UTC. A API pode indexá-lo pela data local
                // (dia anterior ao UTC para jogos noturnos BRT). Buscamos o dia anterior
                // uma única vez por data e tentamos resolver o jogo nele.
                if ($prevDateItems === null) {
                    $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
                    try {
                        $this->requestCount++;
                        $prevFixtures = $this->client->fixturesByDate($leagueId, $season, $prevDate, 'UTC');
                        $prevDateItems = (array) data_get($prevFixtures, 'response', []);
                        if ($prevDateItems !== []) {
                            logger()->info('[af-resolver] per-match fallback to previous day', [
                                'league_id' => $leagueId,
                                'queried'   => $date,
                                'fallback'  => $prevDate,
                                'found'     => count($prevDateItems),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $this->failureCount++;
                        $prevDateItems = [];
                    }
                }

                if ($prevDateItems !== []) {
                    $id = $this->resolveFixtureIdForMatch($match, $prevDateItems);
                    if ($id > 0) {
                        $fixtureIdByMatch[(int) $match->id] = $id;
                    }
                }
            }
        }

        return $fixtureIdByMatch;
    }

    /**
     * @param array<int, int> $fixtureIds
     * @return array<int, array>
     */
    public function fetchFixtureDetailsByIds(array $fixtureIds): array
    {
        $detailByFixtureId = [];

        foreach (array_chunk(array_values(array_unique($fixtureIds)), 20) as $chunk) {
            try {
                $this->requestCount++;
                $payload = $this->client->fixturesByIds($chunk);
            } catch (\Throwable $e) {
                $this->failureCount++;
                throw $e;
            }

            foreach ((array) data_get($payload, 'response', []) as $fixturePayload) {
                $fixtureId = (int) data_get($fixturePayload, 'fixture.id', 0);
                if ($fixtureId > 0) {
                    $detailByFixtureId[$fixtureId] = $fixturePayload;
                }
            }
        }

        return $detailByFixtureId;
    }

    private function resolveFixtureIdForMatch(FootballMatch $match, array $fixtures): int
    {
        $home = $this->normalize((string) ($match->homeTeam?->name ?? $match->homeTeam?->short_name ?? $match->homeTeam?->tla ?? ''));
        $away = $this->normalize((string) ($match->awayTeam?->name ?? $match->awayTeam?->short_name ?? $match->awayTeam?->tla ?? ''));
        $homeCanonical = $this->canonicalClubName($home);
        $awayCanonical = $this->canonicalClubName($away);
        $kickoff = $match->utc_date?->copy()->utc();

        foreach ($fixtures as $fixture) {
            $fixtureHome = $this->normalize((string) data_get($fixture, 'teams.home.name', ''));
            $fixtureAway = $this->normalize((string) data_get($fixture, 'teams.away.name', ''));
            $fixtureHomeCanonical = $this->canonicalClubName($fixtureHome);
            $fixtureAwayCanonical = $this->canonicalClubName($fixtureAway);
            $fixtureId = (int) data_get($fixture, 'fixture.id', 0);

            $homeMatch = $this->teamNamesMatch($home, $homeCanonical, $fixtureHome, $fixtureHomeCanonical);
            $awayMatch = $this->teamNamesMatch($away, $awayCanonical, $fixtureAway, $fixtureAwayCanonical);

            if (! $homeMatch || ! $awayMatch) {
                logger()->debug('[af-resolver] candidate rejected (name mismatch)', [
                    'match_id'       => $match->id,
                    'fd_home'        => $home,
                    'fd_away'        => $away,
                    'af_home'        => $fixtureHome,
                    'af_away'        => $fixtureAway,
                    'af_fixture_id'  => $fixtureId,
                    'home_match'     => $homeMatch,
                    'away_match'     => $awayMatch,
                ]);
                continue;
            }

            $fixtureDate = data_get($fixture, 'fixture.date');
            if (! is_string($fixtureDate) || ! $kickoff) {
                logger()->info('[af-resolver] resolved (no date check)', ['match_id' => $match->id, 'fixture_id' => $fixtureId]);
                return $fixtureId;
            }

            $candidate = Carbon::parse($fixtureDate)->utc();
            $diffMin = abs($candidate->diffInMinutes($kickoff));

            if ($diffMin <= 180) {
                logger()->info('[af-resolver] resolved', ['match_id' => $match->id, 'fixture_id' => $fixtureId, 'diff_min' => $diffMin]);
                return $fixtureId;
            }

            logger()->debug('[af-resolver] candidate rejected (date mismatch)', [
                'match_id'      => $match->id,
                'fd_home'       => $home,
                'fd_away'       => $away,
                'af_fixture_id' => $fixtureId,
                'kickoff_utc'   => $kickoff->toIso8601String(),
                'fixture_utc'   => $candidate->toIso8601String(),
                'diff_min'      => $diffMin,
            ]);
        }

        logger()->warning('[af-resolver] no fixture found', [
            'match_id'         => $match->id,
            'fd_home'          => $home,
            'fd_away'          => $away,
            'kickoff_utc'      => $kickoff?->toIso8601String(),
            'candidates_count' => count($fixtures),
        ]);

        return 0;
    }

    private function normalize(string $value): string
    {
        $normalized = Str::ascii($value);
        $normalized = mb_strtoupper($normalized);
        $normalized = preg_replace('/[^A-Z0-9 ]/u', '', $normalized) ?? '';

        return trim($normalized);
    }

    private function canonicalClubName(string $normalizedName): string
    {
        $prefixes = [
            'CLUBE DE REGATAS',
            'CLUBE DE',
            'ASSOCIACAO ATLETICA',
            'ASSOCIACAO',
            'SOCIEDADE ESPORTIVA',
            'ESPORTE CLUBE',
            'CLUBE ATLETICO',
            'SPORT CLUB',
            'FOOTBALL CLUB',
            'FUTEBOL CLUBE',
            'SE ',
            'SC ',
            'FC ',
            'EC ',
            'CR ',
            'AC ',
            'CA ',
            'AA ',
            'CD ',
        ];

        $name = ' '.$normalizedName;
        foreach ($prefixes as $prefix) {
            $pref = ' '.trim($prefix).' ';
            if (str_starts_with($name, $pref)) {
                $name = ' '.substr($name, strlen($pref));
                break;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $name) ?? $normalizedName);
    }

    private function teamNamesMatch(string $baseRaw, string $baseCanonical, string $fixtureRaw, string $fixtureCanonical): bool
    {
        $baseKey = $this->teamAliasKey($baseRaw, $baseCanonical);
        $fixtureKey = $this->teamAliasKey($fixtureRaw, $fixtureCanonical);
        if ($baseKey !== null && $fixtureKey !== null && $baseKey === $fixtureKey) {
            return true;
        }

        if ($baseRaw === $fixtureRaw || $baseCanonical === $fixtureCanonical) {
            return true;
        }

        if ($baseCanonical !== '' && $fixtureCanonical !== '') {
            if (str_contains($baseCanonical, $fixtureCanonical) || str_contains($fixtureCanonical, $baseCanonical)) {
                return true;
            }
        }

        // Normaliza variações comuns no futebol brasileiro (ex.: "CA MINEIRO" vs "ATLETICO MG").
        $normalizeSynonyms = static function (string $name): string {
            $n = preg_replace('/\s+/', ' ', trim($name)) ?: $name;
            $replace = [
                'ATLETICO MINEIRO' => 'ATLETICO MG',
                'MINEIRO' => 'ATLETICO MG',
                'VASCO DA GAMA' => 'VASCO',
                'MIRASSOL FC' => 'MIRASSOL',
            ];
            foreach ($replace as $from => $to) {
                if ($n === $from || str_contains($n, $from)) {
                    $n = str_replace($from, $to, $n);
                }
            }
            return trim($n);
        };

        $baseSyn = $normalizeSynonyms($baseCanonical !== '' ? $baseCanonical : $baseRaw);
        $fixtureSyn = $normalizeSynonyms($fixtureCanonical !== '' ? $fixtureCanonical : $fixtureRaw);
        if ($baseSyn !== '' && $fixtureSyn !== '') {
            $compact = static fn (string $v): string => preg_replace('/\s+/', '', $v) ?? $v;
            $baseCmp = $compact($baseSyn);
            $fixtureCmp = $compact($fixtureSyn);
            if ($baseSyn === $fixtureSyn
                || $baseCmp === $fixtureCmp
                || str_contains($baseSyn, $fixtureSyn)
                || str_contains($fixtureSyn, $baseSyn)
                || str_contains($baseCmp, $fixtureCmp)
                || str_contains($fixtureCmp, $baseCmp)) {
                return true;
            }
        }

        return false;
    }

    private function teamAliasKey(string $raw, string $canonical): ?string
    {
        $value = trim($canonical !== '' ? $canonical : $raw);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $compact = preg_replace('/[^A-Z0-9]/', '', $value) ?? $value;

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
}
