<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->boolean('closed_predictions')->default(false)->after('allow_prediction_changes');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->dropColumn('closed_predictions');
        });
    }
};

