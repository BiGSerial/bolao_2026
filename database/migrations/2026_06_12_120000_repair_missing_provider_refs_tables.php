<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('match_provider_refs')) {
            Schema::create('match_provider_refs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
                $table->string('provider', 40);
                $table->unsignedBigInteger('external_id');
                $table->timestamps();

                $table->unique(['provider', 'external_id']);
                $table->unique(['football_match_id', 'provider']);
                $table->index('football_match_id');
            });
        }

        if (! Schema::hasTable('team_provider_refs')) {
            Schema::create('team_provider_refs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('provider', 40);
                $table->unsignedBigInteger('external_id');
                $table->timestamps();

                $table->unique(['provider', 'external_id']);
                $table->unique(['team_id', 'provider']);
                $table->index('team_id');
            });
        }

        if (! Schema::hasTable('match_lineups')) {
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
        }

        if (! Schema::hasTable('match_team_statistics')) {
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
        }

        if (! Schema::hasTable('match_player_statistics')) {
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
    }

    public function down(): void
    {
        // This migration repairs potentially pre-existing tables and must not remove them.
    }
};
