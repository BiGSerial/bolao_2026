<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('push_subscriptions', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('content_encoding');
            }
            if (! Schema::hasColumn('push_subscriptions', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('user_agent');
            }
            if (! Schema::hasColumn('push_subscriptions', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('last_used_at');
            }
        });

        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->index(['user_id', 'revoked_at'], 'push_subscriptions_user_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('push_subscriptions_user_revoked_idx');
            $table->dropColumn(['user_agent', 'last_used_at', 'revoked_at']);
        });
    }
};
