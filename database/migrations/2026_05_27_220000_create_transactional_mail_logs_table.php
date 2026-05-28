<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactional_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('kinghost_smtp');
            $table->string('from_address');
            $table->string('to_address');
            $table->string('subject');
            $table->string('status')->default('queued');
            $table->string('external_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['to_address']);
            $table->index(['external_id']);
            $table->index(['created_at']);
            $table->index(['sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_mail_logs');
    }
};

