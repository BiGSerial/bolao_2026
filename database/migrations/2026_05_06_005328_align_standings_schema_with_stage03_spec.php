<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standings', function (Blueprint $table): void {
            if (! Schema::hasColumn('standings', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('group_name');
            }
        });

        Schema::table('standing_rows', function (Blueprint $table): void {
            if (! Schema::hasColumn('standing_rows', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('points');
            }
        });

        Schema::table('standings', function (Blueprint $table): void {
            $table->index(['competition_id', 'competition_season_id'], 'standings_competition_season_idx');
            $table->index(['stage', 'group_name'], 'standings_stage_group_idx');
        });

        Schema::table('standing_rows', function (Blueprint $table): void {
            $table->index(['standing_id', 'position'], 'standing_rows_standing_position_idx');
        });
    }

    public function down(): void
    {
        Schema::table('standing_rows', function (Blueprint $table): void {
            $table->dropIndex('standing_rows_standing_position_idx');
        });

        Schema::table('standings', function (Blueprint $table): void {
            $table->dropIndex('standings_competition_season_idx');
            $table->dropIndex('standings_stage_group_idx');
        });

        Schema::table('standing_rows', function (Blueprint $table): void {
            if (Schema::hasColumn('standing_rows', 'raw_payload')) {
                $table->dropColumn('raw_payload');
            }
        });

        Schema::table('standings', function (Blueprint $table): void {
            if (Schema::hasColumn('standings', 'raw_payload')) {
                $table->dropColumn('raw_payload');
            }
        });
    }
};
