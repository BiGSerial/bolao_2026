<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_event_id')->nullable()->constrained('match_events')->nullOnDelete();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pool_id')->nullable()->constrained('pools')->nullOnDelete();
            $table->string('type', 40);
            $table->string('channel', 120)->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['football_match_id', 'type'], 'match_notifications_match_type_idx');
            $table->index(['user_id', 'status'], 'match_notifications_user_status_idx');
            $table->index(['pool_id', 'status'], 'match_notifications_pool_status_idx');
            $table->index(['match_event_id', 'user_id'], 'match_notifications_event_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_notifications');
    }
};
