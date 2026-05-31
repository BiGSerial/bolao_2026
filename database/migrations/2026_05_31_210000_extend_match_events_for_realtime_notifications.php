<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->index(['provider', 'provider_fixture_id'], 'match_events_provider_fixture_idx');
            $table->index('provider_event_id', 'match_events_provider_event_id_idx');
            $table->unique('fingerprint', 'match_events_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table): void {
            $table->dropUnique('match_events_fingerprint_unique');
            $table->dropIndex('match_events_provider_fixture_idx');
            $table->dropIndex('match_events_provider_event_id_idx');

            $table->dropColumn([
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
            ]);
        });
    }
};
