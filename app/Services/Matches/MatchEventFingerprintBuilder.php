<?php

namespace App\Services\Matches;

class MatchEventFingerprintBuilder
{
    public function build(array $normalized): string
    {
        $parts = [
            (string) ($normalized['provider'] ?? ''),
            (string) ($normalized['provider_fixture_id'] ?? ''),
            (string) ($normalized['event_type'] ?? ''),
            (string) ($normalized['event_detail'] ?? ''),
            (string) ($normalized['team_id'] ?? ''),
            (string) ($normalized['player_id'] ?? ''),
            (string) ($normalized['assist_player_id'] ?? ''),
            (string) ($normalized['minute'] ?? ''),
            (string) ($normalized['extra_minute'] ?? ''),
        ];

        return hash('sha256', implode('|', $parts));
    }
}
