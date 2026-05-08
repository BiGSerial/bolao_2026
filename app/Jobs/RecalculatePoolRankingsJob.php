<?php

namespace App\Jobs;

use App\Events\PoolRankingUpdated;
use App\Models\Pool;
use App\Services\Pools\PoolRankingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculatePoolRankingsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $poolId)
    {
        $this->onQueue(config('queue-priority.jobs.ranking', 'ranking'));
    }

    public function handle(PoolRankingService $service): void
    {
        $pool = Pool::find($this->poolId);
        if ($pool) {
            $service->recalculate($pool);
            PoolRankingUpdated::dispatch($pool);
        }
    }
}
