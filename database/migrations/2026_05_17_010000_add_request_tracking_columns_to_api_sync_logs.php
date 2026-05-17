<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_sync_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('api_sync_logs', 'is_request_log')) {
                $table->boolean('is_request_log')->default(false)->after('success');
            }
            if (! Schema::hasColumn('api_sync_logs', 'request_method')) {
                $table->string('request_method', 10)->nullable()->after('is_request_log');
            }
            if (! Schema::hasColumn('api_sync_logs', 'request_url')) {
                $table->string('request_url')->nullable()->after('request_method');
            }
            if (! Schema::hasColumn('api_sync_logs', 'request_query')) {
                $table->json('request_query')->nullable()->after('request_url');
            }
            if (! Schema::hasColumn('api_sync_logs', 'response_payload')) {
                $table->json('response_payload')->nullable()->after('request_query');
            }
            if (! Schema::hasColumn('api_sync_logs', 'request_started_at')) {
                $table->timestamp('request_started_at')->nullable()->after('response_payload');
            }
            if (! Schema::hasColumn('api_sync_logs', 'request_finished_at')) {
                $table->timestamp('request_finished_at')->nullable()->after('request_started_at');
            }
            if (! Schema::hasColumn('api_sync_logs', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('request_finished_at');
            }

            $table->index(['is_request_log', 'provider', 'synced_at'], 'api_sync_logs_request_idx');
        });
    }

    public function down(): void
    {
        Schema::table('api_sync_logs', function (Blueprint $table): void {
            $table->dropIndex('api_sync_logs_request_idx');
            $columns = [
                'is_request_log',
                'request_method',
                'request_url',
                'request_query',
                'response_payload',
                'request_started_at',
                'request_finished_at',
                'duration_ms',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('api_sync_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
