<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pool_chat_messages', function (Blueprint $table): void {
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('pool_chat_message_edits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('pool_chat_messages')->cascadeOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('old_body');
            $table->text('new_body');
            $table->timestamp('edited_at');
            $table->timestamps();

            $table->index(['message_id', 'edited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_chat_message_edits');

        Schema::table('pool_chat_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by');
        });
    }
};
