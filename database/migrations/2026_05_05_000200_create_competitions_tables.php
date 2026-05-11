<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('competitions')) {
            return;
        }

        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('emblem')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index(['provider', 'code']);
        });

        Schema::create('competition_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->unsignedSmallInteger('year');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('current_matchday')->nullable();
            $table->json('winner_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->unique(['competition_id', 'year']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('tla', 5)->nullable();
            $table->string('crest')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index('tla');
        });

        Schema::create('football_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->dateTime('utc_date');
            $table->dateTime('local_date')->nullable();
            $table->string('status');
            $table->unsignedSmallInteger('matchday')->nullable();
            $table->string('stage')->nullable();
            $table->string('group_name')->nullable();
            $table->string('score_winner')->nullable();
            $table->string('score_duration')->nullable();
            $table->unsignedTinyInteger('home_score_full_time')->nullable();
            $table->unsignedTinyInteger('away_score_full_time')->nullable();
            $table->unsignedTinyInteger('home_score_half_time')->nullable();
            $table->unsignedTinyInteger('away_score_half_time')->nullable();
            $table->unsignedTinyInteger('home_score_extra_time')->nullable();
            $table->unsignedTinyInteger('away_score_extra_time')->nullable();
            $table->unsignedTinyInteger('home_score_penalties')->nullable();
            $table->unsignedTinyInteger('away_score_penalties')->nullable();
            $table->timestamp('last_updated_by_provider_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
            $table->index(['competition_id', 'competition_season_id']);
            $table->index(['stage', 'group_name']);
            $table->index(['utc_date', 'status']);
        });

        Schema::create('standings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competition_season_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('football_data');
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('stage')->nullable();
            $table->string('type')->nullable();
            $table->string('group_name')->nullable();
            $table->timestamps();
        });

        Schema::create('standing_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('standing_id')->constrained('standings')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedTinyInteger('position')->nullable();
            $table->unsignedSmallInteger('played_games')->default(0);
            $table->unsignedSmallInteger('won')->default(0);
            $table->unsignedSmallInteger('draw')->default(0);
            $table->unsignedSmallInteger('lost')->default(0);
            $table->smallInteger('goal_difference')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->json('form_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('api_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('football_data');
            $table->string('endpoint');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('records_total')->default(0);
            $table->unsignedInteger('records_changed')->default(0);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['provider', 'success', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_logs');
        Schema::dropIfExists('standing_rows');
        Schema::dropIfExists('standings');
        Schema::dropIfExists('football_matches');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('competition_seasons');
        Schema::dropIfExists('competitions');
    }
};
