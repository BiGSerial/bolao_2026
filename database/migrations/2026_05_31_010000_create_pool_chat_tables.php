<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pool_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()->constrained('pool_chat_messages')->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['pool_id', 'id']);
            $table->index(['pool_id', 'created_at']);
        });

        Schema::create('pool_chat_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('pool_chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji'], 'pool_chat_reactions_unique');
            $table->index(['message_id', 'emoji']);
        });

        Schema::create('pool_chat_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('pool_chat_messages')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'user_id'], 'pool_chat_reads_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pool_chat_reads');
        Schema::dropIfExists('pool_chat_message_reactions');
        Schema::dropIfExists('pool_chat_messages');
    }
};
