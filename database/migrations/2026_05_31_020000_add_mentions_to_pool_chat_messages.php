<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pool_chat_messages', function (Blueprint $table): void {
            $table->json('mentioned_user_ids')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('pool_chat_messages', function (Blueprint $table): void {
            $table->dropColumn('mentioned_user_ids');
        });
    }
};
