<?php

namespace Tests\Feature\Domain;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\Team;
use App\Services\Api\Connectors\ApiFootballConnector;
use App\Services\ApiFootball\ApiFootballClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ApiFootballConnectorTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_23h_brazil_match_using_raw_utc_instant_and_country_alias(): void
    {
        $competition = Competition::create([
            'provider' => 'football_data',
            'external_id' => 2000,
            'code' => 'WC',
            'name' => 'World Cup',
        ]);
        $season = CompetitionSeason::create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 2398,
            'year' => 2026,
        ]);
        $home = Team::create([
            'provider' => 'football_data',
            'external_id' => 1,
            'name' => 'South Korea',
        ]);
        $away = Team::create([
            'provider' => 'football_data',
            'external_id' => 2,
            'name' => 'Czechia',
        ]);
        $match = FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => 5001,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => '2026-06-12 02:00:00',
            'local_date' => '2026-06-11 23:00:00',
            'status' => 'TIMED',
            'stage' => 'GROUP_STAGE',
        ])->load(['homeTeam', 'awayTeam']);

        $client = Mockery::mock(ApiFootballClient::class);
        $client->shouldReceive('fixturesByDate')
            ->once()
            ->with(1, 2026, '2026-06-12', 'UTC')
            ->andReturn([
                'response' => [[
                    'fixture' => [
                        'id' => 1538999,
                        'date' => '2026-06-12T02:00:00+00:00',
                    ],
                    'teams' => [
                        'home' => ['name' => 'South Korea'],
                        'away' => ['name' => 'Czech Republic'],
                    ],
                ]],
            ]);

        $resolved = (new ApiFootballConnector($client))
            ->resolveFixtureIds(collect([$match]), 1, 2026);

        $this->assertSame('2026-06-12T02:00:00+00:00', $match->kickoffAtUtc()?->toIso8601String());
        $this->assertSame('2026-06-11T23:00:00-03:00', $match->kickoffAtBrazil()?->toIso8601String());
        $this->assertSame(1538999, $resolved[(int) $match->id]);
    }
}
