<?php

namespace Tests\Feature\Domain;

use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\FootballMatch;
use App\Models\FootballMatchDetail;
use App\Models\MatchProviderRef;
use App\Models\Team;
use App\Services\Api\Connectors\ApiFootballConnector;
use App\Services\Api\Connectors\FootballDataConnector;
use App\Services\FootballData\SyncWorldCupMatchDetailsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Tests\TestCase;

class ApiIntegrationPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', env('DB_HOST', 'mysql'));
        config()->set('database.connections.mysql.port', (int) env('DB_PORT', 3306));
        config()->set('database.connections.mysql.database', 'bolao');
        config()->set('database.connections.mysql.username', 'bolao');
        config()->set('database.connections.mysql.password', 'bolao123');

        $this->rebuildMinimalSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_persists_base_detail_from_connector(): void
    {
        config()->set('api-football.enabled', false);

        $match = $this->seedMatch();

        $footballDataConnector = Mockery::mock(FootballDataConnector::class);
        $footballDataConnector
            ->shouldReceive('fetchDetailsBatch')
            ->once()
            ->andReturn([
                $match->id => ['payload' => ['match' => ['id' => 999], 'score' => ['fullTime' => ['home' => 1, 'away' => 0]]]],
            ]);

        $apiFootballConnector = Mockery::mock(ApiFootballConnector::class);
        $apiFootballConnector->shouldNotReceive('resolveFixtureIds');
        $apiFootballConnector->shouldNotReceive('fetchFixtureDetailsByIds');

        $this->app->instance(FootballDataConnector::class, $footballDataConnector);
        $this->app->instance(ApiFootballConnector::class, $apiFootballConnector);

        $result = app(SyncWorldCupMatchDetailsService::class)->syncBatch(1, 'BSA', 2026, 'REGULAR_SEASON');

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame(0, $result['enriched']);

        $detail = FootballMatchDetail::query()->where('football_match_id', $match->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('football_data', $detail->provider);
        $this->assertArrayNotHasKey('_api_football', (array) $detail->payload);
    }

    public function test_sync_persists_match_provider_ref_and_projects_details_when_enabled(): void
    {
        config()->set('api-football.enabled', true);
        config()->set('api-football.token', 'test-token');
        config()->set('api-football.competitions.BSA.league_id', 71);
        config()->set('api-football.competitions.BSA.season', 2026);
        config()->set('api-integration.project_match_details', true);

        $match = $this->seedMatch();

        $footballDataConnector = Mockery::mock(FootballDataConnector::class);
        $footballDataConnector
            ->shouldReceive('fetchDetailsBatch')
            ->once()
            ->andReturn([
                $match->id => ['payload' => ['match' => ['id' => 999], 'score' => ['fullTime' => ['home' => 1, 'away' => 0]]]],
            ]);

        $apiPayload = [
            'fixture' => ['id' => 555, 'status' => ['short' => 'FT']],
            'teams' => ['home' => ['id' => 123, 'name' => 'Home'], 'away' => ['id' => 456, 'name' => 'Away']],
            'events' => [['time' => ['elapsed' => 15], 'team' => ['name' => 'Home'], 'player' => ['name' => 'P1'], 'assist' => ['name' => 'A1'], 'type' => 'Goal', 'detail' => 'Normal Goal']],
            'lineups' => [['team' => ['name' => 'Home'], 'formation' => '4-3-3', 'startXI' => [['player' => ['name' => 'P1']]], 'substitutes' => [], 'coach' => ['name' => 'Coach']]],
            'statistics' => [['team' => ['name' => 'Home'], 'statistics' => [['type' => 'Shots on Goal', 'value' => 5]]]],
            'players' => [['team' => ['name' => 'Home'], 'players' => [['player' => ['id' => 11, 'name' => 'P1'], 'statistics' => [['games' => ['minutes' => 90]]]]]]],
        ];

        $apiFootballConnector = Mockery::mock(ApiFootballConnector::class);
        $apiFootballConnector
            ->shouldReceive('resolveFixtureIds')
            ->once()
            ->andReturn([$match->id => 555]);
        $apiFootballConnector
            ->shouldReceive('fetchFixtureDetailsByIds')
            ->once()
            ->andReturn([555 => $apiPayload]);

        $this->app->instance(FootballDataConnector::class, $footballDataConnector);
        $this->app->instance(ApiFootballConnector::class, $apiFootballConnector);

        $result = app(SyncWorldCupMatchDetailsService::class)->syncBatch(1, 'BSA', 2026, 'REGULAR_SEASON');

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['enriched']);

        $detail = FootballMatchDetail::query()->where('football_match_id', $match->id)->firstOrFail();
        $this->assertSame('multi', $detail->provider);
        $this->assertArrayHasKey('_api_football', (array) $detail->payload);

        $this->assertDatabaseHas('match_provider_refs', [
            'football_match_id' => $match->id,
            'provider' => 'api_football',
            'external_id' => 555,
        ]);

        $this->assertSame(1, DB::table('match_events')->where('football_match_id', $match->id)->count());
        $this->assertSame(1, DB::table('match_lineups')->where('football_match_id', $match->id)->count());
        $this->assertSame(1, DB::table('match_team_statistics')->where('football_match_id', $match->id)->count());
        $this->assertSame(1, DB::table('match_player_statistics')->where('football_match_id', $match->id)->count());
    }

    public function test_sync_does_not_project_optional_tables_when_disabled(): void
    {
        config()->set('api-football.enabled', true);
        config()->set('api-football.token', 'test-token');
        config()->set('api-football.competitions.BSA.league_id', 71);
        config()->set('api-football.competitions.BSA.season', 2026);
        config()->set('api-integration.project_match_details', false);

        $match = $this->seedMatch();

        $footballDataConnector = Mockery::mock(FootballDataConnector::class);
        $footballDataConnector
            ->shouldReceive('fetchDetailsBatch')
            ->once()
            ->andReturn([
                $match->id => ['payload' => ['match' => ['id' => 999]]],
            ]);

        $apiFootballConnector = Mockery::mock(ApiFootballConnector::class);
        $apiFootballConnector
            ->shouldReceive('resolveFixtureIds')
            ->once()
            ->andReturn([$match->id => 777]);
        $apiFootballConnector
            ->shouldReceive('fetchFixtureDetailsByIds')
            ->once()
            ->andReturn([777 => ['fixture' => ['id' => 777], 'teams' => ['home' => ['id' => 1], 'away' => ['id' => 2]], 'events' => [['type' => 'Goal']]]]);

        $this->app->instance(FootballDataConnector::class, $footballDataConnector);
        $this->app->instance(ApiFootballConnector::class, $apiFootballConnector);

        app(SyncWorldCupMatchDetailsService::class)->syncBatch(1, 'BSA', 2026, 'REGULAR_SEASON');

        $this->assertSame(0, DB::table('match_events')->where('football_match_id', $match->id)->count());
        $this->assertSame(0, DB::table('match_lineups')->where('football_match_id', $match->id)->count());
        $this->assertSame(0, DB::table('match_team_statistics')->where('football_match_id', $match->id)->count());
        $this->assertSame(0, DB::table('match_player_statistics')->where('football_match_id', $match->id)->count());
    }


    private function rebuildMinimalSchema(): void
    {
        $schema = Schema::connection('mysql');

        foreach (['match_player_statistics','match_team_statistics','match_lineups','match_events','match_provider_refs','football_match_details','football_matches','team_provider_refs','teams','competition_seasons','competitions'] as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('type')->nullable();
            $table->timestamps();
        });

        $schema->create('competition_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
        });

        $schema->create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('tla', 5)->nullable();
            $table->timestamps();
        });

        $schema->create('team_provider_refs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->unsignedBigInteger('external_id');
            $table->timestamps();
        });

        $schema->create('football_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('competition_season_id')->constrained('competition_seasons')->cascadeOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->dateTime('utc_date');
            $table->dateTime('local_date')->nullable();
            $table->string('status');
            $table->unsignedSmallInteger('matchday')->nullable();
            $table->string('stage')->nullable();
            $table->unsignedInteger('live_clock_accumulated_seconds')->default(0);
            $table->timestamp('live_clock_anchor_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        $schema->create('football_match_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique('football_match_id');
        });

        $schema->create('match_provider_refs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->unsignedBigInteger('external_id');
            $table->timestamps();
        });

        $schema->create('match_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('api_football');
            $table->unsignedSmallInteger('minute')->nullable();
            $table->unsignedSmallInteger('extra_minute')->nullable();
            $table->string('team_name')->nullable();
            $table->string('player_name')->nullable();
            $table->string('assist_name')->nullable();
            $table->string('event_type', 40)->nullable();
            $table->string('event_detail')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        $schema->create('match_lineups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('api_football');
            $table->string('team_name')->nullable();
            $table->string('formation', 20)->nullable();
            $table->json('start_xi')->nullable();
            $table->json('substitutes')->nullable();
            $table->json('coach')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        $schema->create('match_team_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('api_football');
            $table->string('team_name')->nullable();
            $table->json('statistics')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        $schema->create('match_player_statistics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('api_football');
            $table->string('team_name')->nullable();
            $table->string('player_name')->nullable();
            $table->unsignedBigInteger('provider_player_id')->nullable();
            $table->json('statistics')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    private function seedMatch(): FootballMatch
    {
        $competition = Competition::create([
            'provider' => 'football_data',
            'external_id' => 2000,
            'code' => 'BSA',
            'name' => 'Brasileirao',
            'type' => 'LEAGUE',
        ]);

        $season = CompetitionSeason::create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => 200000,
            'year' => 2026,
        ]);

        $home = Team::create([
            'provider' => 'football_data',
            'external_id' => 10,
            'name' => 'Home',
            'short_name' => 'Home',
            'tla' => 'HOM',
        ]);

        $away = Team::create([
            'provider' => 'football_data',
            'external_id' => 20,
            'name' => 'Away',
            'short_name' => 'Away',
            'tla' => 'AWY',
        ]);

        return FootballMatch::create([
            'provider' => 'football_data',
            'external_id' => 999,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'utc_date' => now()->utc()->subHour(),
            'local_date' => now()->subHour(),
            'status' => 'FINISHED',
            'stage' => 'REGULAR_SEASON',
            'matchday' => 1,
        ]);
    }
}
