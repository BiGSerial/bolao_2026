<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_goals')->default(true);
            $table->boolean('notify_match_start')->default(true);
            $table->boolean('notify_match_finished')->default(true);
            $table->boolean('notify_match_summary')->default(true);
            $table->boolean('notify_pool_ranking')->default(true);
            $table->boolean('notify_only_my_predictions')->default(false);
            $table->boolean('notify_sound')->default(true);
            $table->timestamps();

            $table->unique('user_id', 'user_notification_preferences_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
