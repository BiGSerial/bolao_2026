<?php

namespace App\Jobs;

use App\Models\FootballMatch;
use App\Models\Prediction;
use App\Services\Predictions\PredictionScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CalculatePredictionsForMatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $footballMatchId)
    {
        $this->onQueue(config('queue-priority.jobs.scoring', 'scoring'));
    }

    public function handle(PredictionScoringService $service): void
    {
        $match = FootballMatch::query()->find($this->footballMatchId);
        if (! $match || $match->status !== 'FINISHED') {
            return;
        }

        Prediction::query()
            ->where('football_match_id', $this->footballMatchId)
            ->chunkById(200, fn ($predictions) => $predictions->each(fn (Prediction $prediction) => $service->calculate($prediction)));
    }
}
