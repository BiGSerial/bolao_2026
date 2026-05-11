<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('match_events')) {
            return;
        }

        Schema::create('match_events', function (Blueprint $table) {
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

            $table->index(['football_match_id', 'provider']);
            $table->index(['event_type', 'minute']);
        });

        Schema::create('match_lineups', function (Blueprint $table) {
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

            $table->unique(['football_match_id', 'provider', 'team_name'], 'match_lineups_unique_team');
        });

        Schema::create('match_team_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('api_football');
            $table->string('team_name')->nullable();
            $table->json('statistics')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['football_match_id', 'provider', 'team_name'], 'match_team_stats_unique_team');
        });

        Schema::create('match_player_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('api_football');
            $table->string('team_name')->nullable();
            $table->string('player_name')->nullable();
            $table->unsignedBigInteger('provider_player_id')->nullable();
            $table->json('statistics')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['football_match_id', 'provider']);
            $table->index('provider_player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_player_statistics');
        Schema::dropIfExists('match_team_statistics');
        Schema::dropIfExists('match_lineups');
        Schema::dropIfExists('match_events');
    }
};
