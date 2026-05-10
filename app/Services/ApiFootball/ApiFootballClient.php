<?php

namespace App\Services\ApiFootball;

use App\Services\Api\GenericApiClient;
use RuntimeException;

class ApiFootballClient
{
    private ?GenericApiClient $client = null;

    public function countries(): array
    {
        return $this->get('/countries');
    }

    public function leagues(?int $season = null, ?bool $current = null): array
    {
        $query = [];

        if ($season !== null) {
            $query['season'] = $season;
        }

        if ($current !== null) {
            $query['current'] = $current ? 'true' : 'false';
        }

        return $this->get('/leagues', $query);
    }

    public function fixtures(int $league, int $season, ?string $status = null): array
    {
        $query = [
            'league' => $league,
            'season' => $season,
        ];

        if ($status !== null && $status !== '') {
            $query['status'] = $status;
        }

        return $this->get('/fixtures', $query);
    }

    public function fixturesByDate(int $league, int $season, string $date, string $timezone = 'UTC'): array
    {
        return $this->get('/fixtures', [
            'league' => $league,
            'season' => $season,
            'date' => $date,
            'timezone' => $timezone,
        ]);
    }

    /**
     * @param array<int, int> $fixtureIds
     */
    public function fixturesByIds(array $fixtureIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $fixtureIds), fn (int $v) => $v > 0)));
        if ($ids === []) {
            return ['response' => []];
        }

        return $this->get('/fixtures', [
            'ids' => implode('-', $ids),
        ]);
    }

    public function fixtureById(int $fixtureId): array
    {
        return $this->get('/fixtures', ['id' => $fixtureId]);
    }

    public function standings(int $league, int $season): array
    {
        return $this->get('/standings', [
            'league' => $league,
            'season' => $season,
        ]);
    }

    public function teams(int $league, int $season): array
    {
        return $this->get('/teams', [
            'league' => $league,
            'season' => $season,
        ]);
    }

    public function get(string $endpoint, array $query = []): array
    {
        return $this->client()->get($endpoint, $query)->json();
    }

    private function client(): GenericApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $token = (string) config('api-football.token');
        if ($token === '') {
            throw new RuntimeException('API_FOOTBALL_TOKEN nao configurado.');
        }

        $this->client = new GenericApiClient((array) config('api-football'));

        return $this->client;
    }
}
