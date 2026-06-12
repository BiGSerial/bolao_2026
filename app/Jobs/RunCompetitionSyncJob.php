<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunCompetitionSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $code,
        private readonly int $season,
        private readonly string $stage
    ) {
        $this->onQueue(config('queue-priority.sync.api', 'api-sync'));
    }

    public function handle(): void
    {
        if ($this->paidApiHasPriorityNow()) {
            Log::channel('smart_sync')->info('[smart-sync] football-data job skipped: paid API priority', [
                'code' => $this->code,
                'season' => $this->season,
                'stage' => $this->stage,
                'now_utc' => now()->utc()->toDateTimeString(),
            ]);

            return;
        }

        Artisan::call('worldcup:sync-group-stage', [
            '--code' => $this->code,
            '--season' => $this->season,
            '--stage' => $this->stage,
            '--force' => true,
        ]);
    }

    private function paidApiHasPriorityNow(): bool
    {
        $nowUtc = now()->utc();
        $liveStatuses = ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'];
        $nonTerminalStatuses = ['TIMED', 'SCHEDULED', ...$liveStatuses];

        return FootballMatch::query()
            ->whereHas('competition', fn ($query) => $query->where('code', strtoupper($this->code)))
            ->whereHas('season', fn ($query) => $query->where('year', $this->season))
            ->where(function ($query) use ($liveStatuses, $nonTerminalStatuses, $nowUtc): void {
                $query->whereIn('status', $liveStatuses)
                    ->orWhere(function ($windowQuery) use ($nonTerminalStatuses, $nowUtc): void {
                        $windowQuery
                            ->whereIn('status', $nonTerminalStatuses)
                            ->whereBetween('utc_date', [$nowUtc->copy()->subHours(3), $nowUtc]);
                    });
            })
            ->exists();
    }
}
