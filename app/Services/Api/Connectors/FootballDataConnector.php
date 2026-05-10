<?php

namespace App\Services\Api\Connectors;

use App\Services\FootballData\FootballDataClient;
use Illuminate\Support\Collection;

class FootballDataConnector
{
    public function __construct(private readonly FootballDataClient $client)
    {
    }

    /**
     * @param Collection<int, \App\Models\FootballMatch> $matches
     * @return array<int, array{payload?: array, error?: string}>
     */
    public function fetchDetailsBatch(Collection $matches): array
    {
        $result = [];

        foreach ($matches as $match) {
            try {
                $result[(int) $match->id] = [
                    'payload' => $this->client->matchDetail((int) $match->external_id),
                ];
            } catch (\Throwable $e) {
                $result[(int) $match->id] = ['error' => $e->getMessage()];
            }
        }

        return $result;
    }
}
