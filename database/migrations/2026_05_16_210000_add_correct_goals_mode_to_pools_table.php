<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            if (! Schema::hasColumn('pools', 'correct_goals_mode')) {
                $table->string('correct_goals_mode', 20)
                    ->default('both_teams')
                    ->after('points_correct_goals');
            }
        });

        DB::table('pools')
            ->whereNull('correct_goals_mode')
            ->update(['correct_goals_mode' => 'both_teams']);
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            if (Schema::hasColumn('pools', 'correct_goals_mode')) {
                $table->dropColumn('correct_goals_mode');
            }
        });
    }
};

