<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('match_events')) {
            Schema::create('match_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
                $table->string('provider', 40)->default('api_football');
                $table->string('provider_event_id', 120)->nullable();
                $table->unsignedBigInteger('provider_fixture_id')->nullable();
                $table->unsignedSmallInteger('minute')->nullable();
                $table->unsignedSmallInteger('extra_minute')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('team_name')->nullable();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->string('player_name')->nullable();
                $table->unsignedBigInteger('assist_player_id')->nullable();
                $table->string('assist_name')->nullable();
                $table->string('event_type', 40)->nullable();
                $table->string('event_detail')->nullable();
                $table->unsignedSmallInteger('home_score')->nullable();
                $table->unsignedSmallInteger('away_score')->nullable();
                $table->unsignedSmallInteger('team_goal_number')->nullable();
                $table->unsignedSmallInteger('player_goal_number')->nullable();
                $table->json('raw_payload')->nullable();
                $table->string('fingerprint', 128)->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->index(['football_match_id', 'provider']);
                $table->index(['event_type', 'minute']);
                $table->index(['provider', 'provider_fixture_id'], 'match_events_provider_fixture_idx');
                $table->index('provider_event_id', 'match_events_provider_event_id_idx');
                $table->unique('fingerprint', 'match_events_fingerprint_unique');
            });

            return;
        }

        Schema::table('match_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('match_events', 'provider_event_id')) {
                $table->string('provider_event_id', 120)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('match_events', 'provider_fixture_id')) {
                $table->unsignedBigInteger('provider_fixture_id')->nullable()->after('provider_event_id');
            }
            if (! Schema::hasColumn('match_events', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('extra_minute');
            }
            if (! Schema::hasColumn('match_events', 'player_id')) {
                $table->unsignedBigInteger('player_id')->nullable()->after('player_name');
            }
            if (! Schema::hasColumn('match_events', 'assist_player_id')) {
                $table->unsignedBigInteger('assist_player_id')->nullable()->after('assist_name');
            }
            if (! Schema::hasColumn('match_events', 'home_score')) {
                $table->unsignedSmallInteger('home_score')->nullable()->after('event_detail');
            }
            if (! Schema::hasColumn('match_events', 'away_score')) {
                $table->unsignedSmallInteger('away_score')->nullable()->after('home_score');
            }
            if (! Schema::hasColumn('match_events', 'team_goal_number')) {
                $table->unsignedSmallInteger('team_goal_number')->nullable()->after('away_score');
            }
            if (! Schema::hasColumn('match_events', 'player_goal_number')) {
                $table->unsignedSmallInteger('player_goal_number')->nullable()->after('team_goal_number');
            }
            if (! Schema::hasColumn('match_events', 'fingerprint')) {
                $table->string('fingerprint', 128)->nullable()->after('raw_payload');
            }
            if (! Schema::hasColumn('match_events', 'notified_at')) {
                $table->timestamp('notified_at')->nullable()->after('fingerprint');
            }
        });

        Schema::table('match_events', function (Blueprint $table): void {
            if (! Schema::hasIndex('match_events', 'match_events_provider_fixture_idx')) {
                $table->index(['provider', 'provider_fixture_id'], 'match_events_provider_fixture_idx');
            }
            if (! Schema::hasIndex('match_events', 'match_events_provider_event_id_idx')) {
                $table->index('provider_event_id', 'match_events_provider_event_id_idx');
            }
            if (! Schema::hasIndex('match_events', 'match_events_fingerprint_unique')) {
                $table->unique('fingerprint', 'match_events_fingerprint_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('match_events')) {
            return;
        }

        Schema::table('match_events', function (Blueprint $table): void {
            if (Schema::hasIndex('match_events', 'match_events_fingerprint_unique')) {
                $table->dropUnique('match_events_fingerprint_unique');
            }
            if (Schema::hasIndex('match_events', 'match_events_provider_fixture_idx')) {
                $table->dropIndex('match_events_provider_fixture_idx');
            }
            if (Schema::hasIndex('match_events', 'match_events_provider_event_id_idx')) {
                $table->dropIndex('match_events_provider_event_id_idx');
            }

            $columns = array_filter([
                'provider_event_id',
                'provider_fixture_id',
                'team_id',
                'player_id',
                'assist_player_id',
                'home_score',
                'away_score',
                'team_goal_number',
                'player_goal_number',
                'fingerprint',
                'notified_at',
            ], fn (string $column): bool => Schema::hasColumn('match_events', $column));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
