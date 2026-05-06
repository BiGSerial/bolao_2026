<?php

namespace Tests\Feature\Domain;

use App\Models\FootballMatch;
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
