<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('football_matches', 'live_clock_accumulated_seconds')) {
            return;
        }

        Schema::table('football_matches', function (Blueprint $table) {
            $table->timestamp('in_play_started_at')->nullable()->after('last_updated_by_provider_at');
            $table->timestamp('interval_started_at')->nullable()->after('in_play_started_at');
            $table->timestamp('resumed_from_interval_at')->nullable()->after('interval_started_at');
            $table->timestamp('finished_at')->nullable()->after('resumed_from_interval_at');
            $table->timestamp('live_clock_anchor_at')->nullable()->after('finished_at');
            $table->unsignedInteger('live_clock_accumulated_seconds')->default(0)->after('live_clock_anchor_at');
        });
    }

    public function down(): void
    {
        Schema::table('football_matches', function (Blueprint $table) {
            $table->dropColumn([
                'in_play_started_at',
                'interval_started_at',
                'resumed_from_interval_at',
                'finished_at',
                'live_clock_anchor_at',
                'live_clock_accumulated_seconds',
            ]);
        });
    }
};
