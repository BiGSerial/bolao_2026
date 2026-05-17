<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('canonical_name_br')->nullable()->after('name');
            $table->index('canonical_name_br');
        });

        // Canonização inicial para clubes com variações comuns no Brasil.
        DB::table('teams')
            ->where(function ($q): void {
                $q->where('name', 'like', '%Atletico-MG%')
                    ->orWhere('name', 'like', '%Atlético-MG%')
                    ->orWhere('name', 'like', '%CA Mineiro%')
                    ->orWhere('short_name', 'like', '%Mineiro%');
            })
            ->update(['canonical_name_br' => 'Atlético-MG']);

        DB::table('teams')
            ->where(function ($q): void {
                $q->where('name', 'like', '%Athletico-PR%')
                    ->orWhere('name', 'like', '%Atletico-PR%')
                    ->orWhere('name', 'like', '%Atlético-PR%')
                    ->orWhere('short_name', 'like', '%Athletico%');
            })
            ->update(['canonical_name_br' => 'Athletico-PR']);
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropIndex(['canonical_name_br']);
            $table->dropColumn('canonical_name_br');
        });
    }
};

