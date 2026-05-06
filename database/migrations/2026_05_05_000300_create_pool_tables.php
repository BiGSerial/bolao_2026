<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('visibility')->default('invite_only');
            $table->string('status')->default('active');
            $table->string('invite_code')->unique();
            $table->boolean('allow_prediction_changes')->default(true);
            $table->unsignedSmallInteger('prediction_lock_minutes')->default(120);
            $table->boolean('allow_pending_member_predictions')->default(true);
            $table->string('stage')->default('GROUP_STAGE');
            $table->timestamps();
            $table->index(['owner_id', 'status']);
            $table->index(['visibility', 'status']);
        });

        Schema::create('pool_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->string('status')->default('pending');
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->unique(['pool_id', 'user_id']);
            $table->index(['pool_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('predictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');
            $table->unsignedInteger('points')->default(0);
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->boolean('eligible')->default(true);
            $table->text('ineligible_reason')->nullable();
            $table->timestamps();
            $table->unique(['pool_id', 'user_id', 'football_match_id'], 'predictions_unique_pool_user_match');
            $table->index(['pool_id', 'eligible']);
            $table->index(['football_match_id', 'calculated_at']);
        });

        Schema::create('prediction_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prediction_id')->constrained('predictions')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['prediction_id', 'changed_at']);
        });

        Schema::create('pool_rankings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_total')->default(0);
            $table->unsignedSmallInteger('exact_scores')->default(0);
            $table->unsignedSmallInteger('correct_results')->default(0);
            $table->unsignedSmallInteger('correct_home_goals')->default(0);
            $table->unsignedSmallInteger('correct_away_goals')->default(0);
            $table->unsignedSmallInteger('predictions_counted')->default(0);
            $table->unsignedSmallInteger('position')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['pool_id', 'user_id']);
            $table->index(['pool_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_rankings');
        Schema::dropIfExists('prediction_versions');
        Schema::dropIfExists('predictions');
        Schema::dropIfExists('pool_members');
        Schema::dropIfExists('pools');
    }
};
