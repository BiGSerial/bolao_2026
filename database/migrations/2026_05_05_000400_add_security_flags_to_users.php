<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('is_admin');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }

            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};
