<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'competition_package_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('subscription_tier')->default(1)->after('is_admin');
            $table->foreignId('competition_package_id')->nullable()->after('subscription_tier')
                ->constrained('competition_packages')->nullOnDelete();

            $table->index(['competition_package_id', 'subscription_tier'], 'users_package_tier_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_package_tier_idx');
            $table->dropConstrainedForeignId('competition_package_id');
            $table->dropColumn('subscription_tier');
        });
    }
};
