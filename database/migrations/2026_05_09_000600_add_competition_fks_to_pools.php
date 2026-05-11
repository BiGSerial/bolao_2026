<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pools', 'competition_id')) {
            return;
        }

        Schema::table('pools', function (Blueprint $table) {
            $table->foreignId('competition_id')->nullable()->after('owner_id')
                ->constrained('competitions')->nullOnDelete();
            $table->foreignId('competition_season_id')->nullable()->after('competition_id')
                ->constrained('competition_seasons')->nullOnDelete();

            $table->index(['competition_id', 'competition_season_id', 'status'], 'pools_competition_season_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropIndex('pools_competition_season_status_idx');
            $table->dropConstrainedForeignId('competition_season_id');
            $table->dropConstrainedForeignId('competition_id');
        });
    }
};
