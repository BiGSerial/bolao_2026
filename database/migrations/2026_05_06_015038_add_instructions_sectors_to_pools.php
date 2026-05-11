<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->text('instructions')->nullable()->after('description');
            $table->json('sectors')->nullable()->after('instructions');
        });

        Schema::table('pool_members', function (Blueprint $table): void {
            $table->string('sector')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table): void {
            $table->dropColumn(['instructions', 'sectors']);
        });

        Schema::table('pool_members', function (Blueprint $table): void {
            $table->dropColumn('sector');
        });
    }
};
