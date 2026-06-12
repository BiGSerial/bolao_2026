<?php

namespace Tests\Feature\Domain;

use App\Models\FootballMatch;
use App\Models\MatchProviderRef;
use App\Services\FootballData\SyncWorldCupMatchesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncWorldCupMatchesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_idempotent_for_same_payload(): void
    {
        $payload = $this->payload('TIMED', null, null);

        $service = app(SyncWorldCupMatchesService::class);
        $service->sync($payload);
        $service->sync($payload);

        $this->assertDatabaseCount('football_matches', 1);
    }

    public function test_sync_updates_score_and_reports_changed_match(): void
    {
        $service = app(SyncWorldCupMatchesService::class);

        $service->sync($this->payload('TIMED', null, null));
        $changed = $service->sync($this->payload('FINISHED', 2, 1));

        $match = FootballMatch::query()->where('external_id', 5001)->firstOrFail();

        $this->assertSame('FINISHED', $match->status);
        $this->assertSame(2, $match->home_score_full_time);
        $this->assertSame(1, $match->away_score_full_time);
        $this->assertCount(1, $changed);
    }

    public function test_sync_supports_non_world_cup_competition_context(): void
    {
        $service = app(SyncWorldCupMatchesService::class);

        $payload = $this->payload('TIMED', null, null);
        $payload['competition']['id'] = 2013;
        $payload['competition']['code'] = 'BSA';
        $payload['competition']['name'] = 'Brasileirao Serie A';
        $payload['competition']['type'] = 'LEAGUE';
        $payload['matches'][0]['season']['id'] = 9001;

        $service->sync($payload, 2026);

        $match = FootballMatch::query()->where('external_id', 5001)->firstOrFail();
        $match->load('competition', 'season');

        $this->assertSame('BSA', $match->competition?->code);
        $this->assertSame(2026, $match->season?->year);
        $this->assertSame('GROUP_STAGE', $match->stage);
    }

    public function test_free_provider_does_not_overwrite_paid_live_state(): void
    {
        $service = app(SyncWorldCupMatchesService::class);
        $service->sync($this->payload('TIMED', null, null));

        $match = FootballMatch::query()->where('external_id', 5001)->firstOrFail();
        $match->update([
            'status' => 'IN_PLAY',
            'home_score_full_time' => 2,
            'away_score_full_time' => 1,
            'home_score_half_time' => 1,
            'away_score_half_time' => 0,
            'last_updated_by_provider_at' => now()->utc(),
            'raw_payload' => [
                'api_football_status' => ['short' => '2H', 'elapsed' => 78],
                'minute' => 78,
            ],
        ]);

        MatchProviderRef::create([
            'football_match_id' => $match->id,
            'provider' => 'api_football',
            'external_id' => 1538999,
        ]);

        $freePayload = $this->payload('FINISHED', 0, 0);
        $freePayload['matches'][0]['score']['halfTime'] = ['home' => 0, 'away' => 0];
        $service->sync($freePayload);

        $match->refresh();

        $this->assertSame('IN_PLAY', $match->status);
        $this->assertSame(2, $match->home_score_full_time);
        $this->assertSame(1, $match->away_score_full_time);
        $this->assertSame(1, $match->home_score_half_time);
        $this->assertSame(0, $match->away_score_half_time);
        $this->assertSame('2H', data_get($match->raw_payload, 'api_football_status.short'));
        $this->assertSame(78, data_get($match->raw_payload, 'minute'));
    }

    public function test_provider_reference_alone_does_not_block_free_final_result(): void
    {
        $service = app(SyncWorldCupMatchesService::class);
        $service->sync($this->payload('TIMED', null, null));

        $match = FootballMatch::query()->where('external_id', 5001)->firstOrFail();
        MatchProviderRef::create([
            'football_match_id' => $match->id,
            'provider' => 'api_football',
            'external_id' => 1538999,
        ]);

        $service->sync($this->payload('FINISHED', 3, 2));
        $match->refresh();

        $this->assertSame('FINISHED', $match->status);
        $this->assertSame(3, $match->home_score_full_time);
        $this->assertSame(2, $match->away_score_full_time);
    }

    private function payload(string $status, ?int $home, ?int $away): array
    {
        return [
            'competition' => [
                'id' => 2000,
                'code' => 'WC',
                'name' => 'FIFA World Cup',
                'type' => 'CUP',
                'emblem' => null,
            ],
            'resultSet' => [
                'count' => 1,
                'first' => '2026-06-11',
                'last' => '2026-06-11',
                'played' => $status === 'FINISHED' ? 1 : 0,
            ],
            'matches' => [[
                'id' => 5001,
                'utcDate' => '2026-06-11T19:00:00Z',
                'status' => $status,
                'stage' => 'GROUP_STAGE',
                'group' => 'GROUP_A',
                'matchday' => 1,
                'lastUpdated' => '2026-06-11T19:00:00Z',
                'season' => [
                    'id' => 2398,
                    'startDate' => '2026-06-11',
                    'endDate' => '2026-07-19',
                    'currentMatchday' => 1,
                    'winner' => null,
                ],
                'homeTeam' => ['id' => 901, 'name' => 'Home', 'shortName' => 'Home', 'tla' => 'HOM', 'crest' => null],
                'awayTeam' => ['id' => 902, 'name' => 'Away', 'shortName' => 'Away', 'tla' => 'AWY', 'crest' => null],
                'score' => [
                    'winner' => null,
                    'duration' => 'REGULAR',
                    'fullTime' => ['home' => $home, 'away' => $away],
                    'halfTime' => ['home' => null, 'away' => null],
                    'extraTime' => ['home' => null, 'away' => null],
                    'penalties' => ['home' => null, 'away' => null],
                ],
            ]],
        ];
    }
}
