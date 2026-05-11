<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_packages')) {
            return;
        }

        Schema::create('competition_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('competition_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_package_id')->constrained('competition_packages')->cascadeOnDelete();
            $table->foreignId('competition_id')->nullable()->constrained('competitions')->nullOnDelete();
            $table->string('competition_code', 12);
            $table->timestamps();

            $table->unique(['competition_package_id', 'competition_code'], 'competition_package_items_pkg_code_unique');
            $table->index(['competition_code', 'competition_package_id'], 'competition_package_items_code_pkg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_package_items');
        Schema::dropIfExists('competition_packages');
    }
};
