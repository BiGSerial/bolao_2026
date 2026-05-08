<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->unsignedTinyInteger('points_exact_score')->default(5)->after('stage');
            $table->unsignedTinyInteger('points_correct_result')->default(3)->after('points_exact_score');
            $table->unsignedTinyInteger('points_correct_goals')->default(1)->after('points_correct_result');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->dropColumn([
                'points_exact_score',
                'points_correct_result',
                'points_correct_goals',
            ]);
        });
    }
};

