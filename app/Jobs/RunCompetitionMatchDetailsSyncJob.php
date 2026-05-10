<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RunCompetitionMatchDetailsSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $code,
        private readonly int $season,
        private readonly string $stage,
        private readonly int $limit
    ) {
        $this->onQueue(config('queue-priority.sync.api_funnel', 'api-funnel'));
    }

    public function handle(): void
    {
        Artisan::call('worldcup:sync-match-details', [
            '--code' => $this->code,
            '--season' => $this->season,
            '--stage' => $this->stage,
            '--limit' => $this->limit,
        ]);
    }
}
