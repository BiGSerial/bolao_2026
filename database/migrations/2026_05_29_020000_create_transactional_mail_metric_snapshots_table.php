<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactional_mail_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('kinghost_smtp');
            $table->date('reference_date');
            $table->string('period_type')->default('daily');

            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedInteger('hard_bounces')->default(0);
            $table->unsignedInteger('openings')->default(0);

            $table->unsignedInteger('total_hired')->nullable();
            $table->unsignedInteger('total_excess_hired')->nullable();
            $table->unsignedInteger('total_consumed')->nullable();
            $table->unsignedInteger('total_exceeded')->nullable();
            $table->unsignedInteger('total_available')->nullable();

            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'period_type', 'reference_date'], 'tm_metrics_provider_period_ref_unique');
            $table->index(['provider', 'reference_date'], 'tm_metrics_provider_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactional_mail_metric_snapshots');
    }
};
