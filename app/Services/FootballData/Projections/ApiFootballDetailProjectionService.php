<?php

namespace App\Services\FootballData\Projections;

use App\Models\FootballMatch;
use Illuminate\Support\Facades\DB;

class ApiFootballDetailProjectionService
{
    public function project(FootballMatch $match, array $payload): void
    {
        DB::transaction(function () use ($match, $payload): void {
            DB::table('match_events')->where('football_match_id', $match->id)->where('provider', 'api_football')->delete();
            DB::table('match_player_statistics')->where('football_match_id', $match->id)->where('provider', 'api_football')->delete();

            foreach ((array) data_get($payload, 'events', []) as $event) {
                DB::table('match_events')->insert([
                    'football_match_id' => $match->id,
                    'provider' => 'api_football',
                    'minute' => data_get($event, 'time.elapsed'),
                    'extra_minute' => data_get($event, 'time.extra'),
                    'team_name' => data_get($event, 'team.name'),
                    'player_name' => data_get($event, 'player.name'),
                    'assist_name' => data_get($event, 'assist.name'),
                    'event_type' => data_get($event, 'type'),
                    'event_detail' => data_get($event, 'detail'),
                    'raw_payload' => json_encode($event),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
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
        });
    }
}
