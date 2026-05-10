<?php

namespace App\Services\FootballData;

use App\Services\Api\GenericApiClient;
use RuntimeException;

class FootballDataClient
{
    private ?GenericApiClient $client = null;

    public function get(string $endpoint, array $query = []): array
    {
        return $this->client()->get($endpoint, $query)->json();
    }

    public function competitionMatches(string $code, int $season, ?string $stage = null, ?string $status = null): array
    {
        $query = ['season' => $season];

        if ($stage !== null && $stage !== '') {
            $query['stage'] = $stage;
        }

        if ($status !== null && $status !== '') {
            $query['status'] = $status;
        }

        return $this->get('/competitions/'.$code.'/matches', $query);
    }

    public function competition(string $code): array
    {
        return $this->get('/competitions/'.$code);
    }

    public function competitionTeams(string $code, int $season): array
    {
        return $this->get('/competitions/'.$code.'/teams', [
            'season' => $season,
        ]);
    }

    public function competitionStandings(string $code, int $season): array
    {
        return $this->get('/competitions/'.$code.'/standings', [
            'season' => $season,
        ]);
    }

    public function matchDetail(int $externalMatchId): array
    {
        return $this->get('/matches/'.$externalMatchId);
    }

    public function defaultCompetitionCode(): string
    {
        return strtoupper((string) config('football-data.default_competition_code', 'WC'));
    }

    /**
     * @return array{code:string,season:int,stage:string|null}
     */
    public function competitionContext(?string $code = null, ?int $season = null, ?string $stage = null): array
    {
        $resolvedCode = strtoupper($code ?: $this->defaultCompetitionCode());
        $settings = (array) config('football-data.competitions.'.$resolvedCode, []);

        return [
            'code' => $resolvedCode,
            'season' => $season ?: (int) ($settings['season'] ?? 0),
            'stage' => $stage ?? (($settings['default_stage'] ?? null) ?: null),
        ];
    }

    // Retrocompatibilidade
    public function worldCupGroupStageMatches(): array
    {
        $ctx = $this->competitionContext('WC');

        return $this->competitionMatches($ctx['code'], $ctx['season'], $ctx['stage']);
    }

    public function worldCupCompetition(): array
    {
        return $this->competition('WC');
    }

    public function worldCupLiveMatches(): array
    {
        $ctx = $this->competitionContext('WC');

        return $this->competitionMatches($ctx['code'], $ctx['season'], $ctx['stage'], 'LIVE');
    }

    public function worldCupTeams(): array
    {
        $ctx = $this->competitionContext('WC');

        return $this->competitionTeams($ctx['code'], $ctx['season']);
    }

    public function worldCupStandings(): array
    {
        $ctx = $this->competitionContext('WC');

        return $this->competitionStandings($ctx['code'], $ctx['season']);
    }

    public function worldCupMatchDetail(int $externalMatchId): array
    {
        return $this->matchDetail($externalMatchId);
    }

    private function client(): GenericApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $token = (string) config('football-data.token');
        if ($token === '') {
            throw new RuntimeException('FOOTBALL_DATA_TOKEN nao configurado.');
        }

        $config = (array) config('football-data');
        $config['auth']['token_header'] = 'X-Auth-Token';

        $this->client = new GenericApiClient($config);

        return $this->client;
    }
}
