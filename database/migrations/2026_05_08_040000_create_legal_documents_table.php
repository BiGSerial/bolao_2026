<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('legal_documents')) {
            return;
        }

        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50);
            $table->string('title');
            $table->string('version', 20);
            $table->longText('content');
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Guarantees a single active document per type. Inactive rows keep NULL and don't collide.
            $table->string('active_type', 50)
                ->nullable()
                ->storedAs("CASE WHEN is_active = 1 THEN type ELSE NULL END");

            $table->unique(['type', 'version']);
            $table->unique('active_type');
            $table->index(['type', 'is_active']);
            $table->index(['type', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
