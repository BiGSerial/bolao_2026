<?php

namespace App\Services\Matches;

use App\Models\FootballMatch;

class MatchEventNormalizer
{
    public function __construct(
        private readonly MatchEventFingerprintBuilder $fingerprintBuilder,
    ) {
    }

    public function normalizeApiFootball(FootballMatch $match, array $event, ?int $providerFixtureId = null): array
    {
        $minute = data_get($event, 'time.elapsed');
        $extraMinute = data_get($event, 'time.extra');

        $normalized = [
            'football_match_id' => (int) $match->id,
            'provider' => 'api_football',
            'provider_event_id' => $this->providerEventId($event),
            'provider_fixture_id' => $providerFixtureId,
            'minute' => is_numeric($minute) ? (int) $minute : null,
            'extra_minute' => is_numeric($extraMinute) ? (int) $extraMinute : null,
            'team_id' => $this->toIntOrNull(data_get($event, 'team.id')),
            'team_name' => $this->toStringOrNull(data_get($event, 'team.name')),
            'player_id' => $this->toIntOrNull(data_get($event, 'player.id')),
            'player_name' => $this->toStringOrNull(data_get($event, 'player.name')),
            'assist_player_id' => $this->toIntOrNull(data_get($event, 'assist.id')),
            'assist_name' => $this->toStringOrNull(data_get($event, 'assist.name')),
            'event_type' => $this->toStringOrNull(data_get($event, 'type')),
            'event_detail' => $this->toStringOrNull(data_get($event, 'detail')),
            'home_score' => $this->toIntOrNull(data_get($event, 'score.home')),
            'away_score' => $this->toIntOrNull(data_get($event, 'score.away')),
            'team_goal_number' => null,
            'player_goal_number' => null,
            'raw_payload' => $event,
        ];

        $normalized['fingerprint'] = $this->fingerprintBuilder->build($normalized);

        return $normalized;
    }

    private function providerEventId(array $event): ?string
    {
        $eventId = data_get($event, 'id');
        if ($eventId === null || $eventId === '') {
            return null;
        }

        return (string) $eventId;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function toStringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
