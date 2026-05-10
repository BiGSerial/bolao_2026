<?php

namespace App\Services\Api\Connectors;

use App\Models\FootballMatch;
use App\Services\ApiFootball\ApiFootballClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;

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
        $groupedByDate = $matches->groupBy(fn (FootballMatch $m) => $m->utc_date?->copy()->utc()->format('Y-m-d'));

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

            foreach ($dateMatches as $match) {
                $id = $this->resolveFixtureIdForMatch($match, $responseItems);
                if ($id > 0) {
                    $fixtureIdByMatch[(int) $match->id] = $id;
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

            if (! $this->teamNamesMatch($home, $homeCanonical, $fixtureHome, $fixtureHomeCanonical)
                || ! $this->teamNamesMatch($away, $awayCanonical, $fixtureAway, $fixtureAwayCanonical)) {
                continue;
            }

            $fixtureDate = data_get($fixture, 'fixture.date');
            if (! is_string($fixtureDate) || ! $kickoff) {
                return (int) data_get($fixture, 'fixture.id', 0);
            }

            $candidate = Carbon::parse($fixtureDate)->utc();
            if (abs($candidate->diffInMinutes($kickoff)) <= 180) {
                return (int) data_get($fixture, 'fixture.id', 0);
            }
        }

        return 0;
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtoupper($value);
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
        if ($baseRaw === $fixtureRaw || $baseCanonical === $fixtureCanonical) {
            return true;
        }

        if ($baseCanonical !== '' && $fixtureCanonical !== '') {
            if (str_contains($baseCanonical, $fixtureCanonical) || str_contains($fixtureCanonical, $baseCanonical)) {
                return true;
            }
        }

        return false;
    }
}
