<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('football_match_details')) {
            return;
        }

        Schema::create('football_match_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->string('provider', 40)->default('football_data');
            $table->unsignedBigInteger('external_id');
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('football_match_id');
            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('football_match_details');
    }
};

