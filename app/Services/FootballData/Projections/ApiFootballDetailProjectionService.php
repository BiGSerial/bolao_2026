<?php

namespace App\Services\FootballData\Projections;

use App\Jobs\Matches\SendGoalNotificationJob;
use App\Models\FootballMatch;
use App\Models\MatchEvent;
use App\Services\Matches\MatchEventGoalClassifier;
use App\Services\Matches\MatchEventNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiFootballDetailProjectionService
{
    public function __construct(
        private readonly MatchEventGoalClassifier $goalClassifier,
        private readonly MatchEventNormalizer $eventNormalizer,
    ) {
    }

    /**
     * @return array{events_total:int,events_new:int,goal_jobs_dispatched:int}
     */
    public function project(FootballMatch $match, array $payload): array
    {
        $result = DB::transaction(function () use ($match, $payload): array {
            $existingByFingerprint = MatchEvent::query()
                ->where('football_match_id', (int) $match->id)
                ->where('provider', 'api_football')
                ->whereNotNull('fingerprint')
                ->get(['fingerprint', 'notified_at'])
                ->keyBy('fingerprint');

            $seenFingerprints = [];
            $newNotifiableGoalEventIds = [];

            DB::table('match_events')->where('football_match_id', $match->id)->where('provider', 'api_football')->delete();
            DB::table('match_player_statistics')->where('football_match_id', $match->id)->where('provider', 'api_football')->delete();
            $providerFixtureId = (int) data_get($payload, 'fixture.id', 0);
            $eventsTotal = 0;
            $eventsNew = 0;

            foreach ((array) data_get($payload, 'events', []) as $event) {
                $normalized = $this->eventNormalizer->normalizeApiFootball(
                    $match,
                    (array) $event,
                    $providerFixtureId > 0 ? $providerFixtureId : null
                );
                $eventsTotal++;

                $fingerprint = (string) ($normalized['fingerprint'] ?? '');
                if ($fingerprint === '' || isset($seenFingerprints[$fingerprint])) {
                    continue;
                }
                $seenFingerprints[$fingerprint] = true;

                $existing = $existingByFingerprint->get($fingerprint);
                $knownFingerprint = $existing !== null;
                if (! $knownFingerprint) {
                    $eventsNew++;
                }

                $matchEventId = DB::table('match_events')->insertGetId([
                    'football_match_id' => $normalized['football_match_id'],
                    'provider' => $normalized['provider'],
                    'provider_event_id' => $normalized['provider_event_id'],
                    'provider_fixture_id' => $normalized['provider_fixture_id'],
                    'minute' => $normalized['minute'],
                    'extra_minute' => $normalized['extra_minute'],
                    'team_id' => $normalized['team_id'],
                    'team_name' => $normalized['team_name'],
                    'player_id' => $normalized['player_id'],
                    'player_name' => $normalized['player_name'],
                    'assist_player_id' => $normalized['assist_player_id'],
                    'assist_name' => $normalized['assist_name'],
                    'event_type' => $normalized['event_type'],
                    'event_detail' => $normalized['event_detail'],
                    'home_score' => $normalized['home_score'],
                    'away_score' => $normalized['away_score'],
                    'team_goal_number' => $normalized['team_goal_number'],
                    'player_goal_number' => $normalized['player_goal_number'],
                    'fingerprint' => $normalized['fingerprint'],
                    'notified_at' => $existing?->notified_at,
                    'raw_payload' => json_encode($normalized['raw_payload']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (! $knownFingerprint && $this->goalClassifier->isNotifiableGoal(
                    (string) ($normalized['event_type'] ?? ''),
                    (string) ($normalized['event_detail'] ?? '')
                )) {
                    $newNotifiableGoalEventIds[] = (int) $matchEventId;
                }
            }

            foreach ((array) data_get($payload, 'lineups', []) as $lineup) {
                DB::table('match_lineups')->updateOrInsert(
                    [
                        'football_match_id' => $match->id,
                        'provider' => 'api_football',
                        'team_name' => (string) data_get($lineup, 'team.name'),
                    ],
                    [
                        'formation' => data_get($lineup, 'formation'),
                        'start_xi' => json_encode((array) data_get($lineup, 'startXI', [])),
                        'substitutes' => json_encode((array) data_get($lineup, 'substitutes', [])),
                        'coach' => json_encode((array) data_get($lineup, 'coach', [])),
                        'raw_payload' => json_encode($lineup),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ((array) data_get($payload, 'statistics', []) as $stats) {
                DB::table('match_team_statistics')->updateOrInsert(
                    [
                        'football_match_id' => $match->id,
                        'provider' => 'api_football',
                        'team_name' => (string) data_get($stats, 'team.name'),
                    ],
                    [
                        'statistics' => json_encode((array) data_get($stats, 'statistics', [])),
                        'raw_payload' => json_encode($stats),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ((array) data_get($payload, 'players', []) as $teamPlayers) {
                $teamName = (string) data_get($teamPlayers, 'team.name');

                foreach ((array) data_get($teamPlayers, 'players', []) as $player) {
                    DB::table('match_player_statistics')->insert([
                        'football_match_id' => $match->id,
                        'provider' => 'api_football',
                        'team_name' => $teamName,
                        'player_name' => data_get($player, 'player.name'),
                        'provider_player_id' => data_get($player, 'player.id'),
                        'statistics' => json_encode((array) data_get($player, 'statistics', [])),
                        'raw_payload' => json_encode($player),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return [
                'events_total' => $eventsTotal,
                'events_new' => $eventsNew,
                'goal_event_ids' => $newNotifiableGoalEventIds,
            ];
        });

        $goalJobsDispatched = 0;
        foreach ((array) ($result['goal_event_ids'] ?? []) as $eventId) {
            SendGoalNotificationJob::dispatch((int) $eventId)
                ->onQueue(config('queue-priority.broadcast.events', 'broadcast'));
            $goalJobsDispatched++;
        }

        Log::channel('smart_sync')->info('[notifications] projection_events_processed', [
            'match_id' => (int) $match->id,
            'events_total' => (int) ($result['events_total'] ?? 0),
            'events_new' => (int) ($result['events_new'] ?? 0),
            'goal_jobs_dispatched' => $goalJobsDispatched,
        ]);

        return [
            'events_total' => (int) ($result['events_total'] ?? 0),
            'events_new' => (int) ($result['events_new'] ?? 0),
            'goal_jobs_dispatched' => $goalJobsDispatched,
        ];
    }
}
