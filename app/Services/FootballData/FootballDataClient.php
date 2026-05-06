<?php

namespace App\Services\FootballData;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FootballDataClient
{
    public function get(string $endpoint, array $query = []): array
    {
        $token = (string) config('football-data.token');
        if ($token === '') {
            throw new RuntimeException('FOOTBALL_DATA_TOKEN nao configurado.');
        }

        $response = Http::baseUrl(config('football-data.base_url'))
            ->withHeaders(['X-Auth-Token' => $token])
            ->timeout(20)
            ->retry(2, 1000)
            ->get($endpoint, $query);

        if ($response->status() === 429) {
            throw new RuntimeException('Rate limit da football-data atingido.');
        }

        $response->throw();

        return $response->json();
    }

    public function worldCupGroupStageMatches(): array
    {
        return $this->get('/competitions/WC/matches', [
            'season' => config('football-data.world_cup.season'),
            'stage' => config('football-data.world_cup.stage'),
        ]);
    }

    public function worldCupCompetition(): array
    {
        return $this->get('/competitions/WC');
    }

    public function worldCupLiveMatches(): array
    {
        return $this->get('/competitions/WC/matches', [
            'season' => config('football-data.world_cup.season'),
            'stage' => config('football-data.world_cup.stage'),
            'status' => 'LIVE',
        ]);
    }

    public function worldCupTeams(): array
    {
        return $this->get('/competitions/WC/teams', [
            'season' => config('football-data.world_cup.season'),
        ]);
    }

    public function worldCupStandings(): array
    {
        return $this->get('/competitions/WC/standings', [
            'season' => config('football-data.world_cup.season'),
        ]);
    }
}
