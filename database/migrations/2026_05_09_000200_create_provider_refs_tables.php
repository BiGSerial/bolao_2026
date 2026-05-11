<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('match_provider_refs')) {
            return;
        }

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

    public function down(): void
    {
        Schema::dropIfExists('team_provider_refs');
        Schema::dropIfExists('match_provider_refs');
    }
};
