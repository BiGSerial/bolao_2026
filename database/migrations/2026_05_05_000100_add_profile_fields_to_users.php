<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('area')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('status')->default('pending')->after('password');
            $table->boolean('is_admin')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['area', 'phone', 'status', 'is_admin']);
        });
    }
};
