<?php

namespace App\Services\FootballData;

use App\Events\MatchDetailUpdated;
use App\Models\FootballMatch;
use App\Models\FootballMatchDetail;
use Illuminate\Support\Collection;
use Throwable;

class SyncWorldCupMatchDetailsService
{
    public function syncBatch(int $limit = 8): array
    {
        $matches = $this->matchesToSync($limit);
        $updated = 0;
        $errors = 0;

        foreach ($matches as $match) {
            try {
                $payload = app(FootballDataClient::class)->worldCupMatchDetail((int) $match->external_id);
                $existing = FootballMatchDetail::query()->where('football_match_id', $match->id)->first();
                $before = $existing ? md5(json_encode($existing->payload)) : null;
                $after = md5(json_encode($payload));

                FootballMatchDetail::updateOrCreate(
                    ['football_match_id' => $match->id],
                    [
                        'provider' => 'football_data',
                        'external_id' => (int) $match->external_id,
                        'payload' => $payload,
                        'fetched_at' => now(),
                        'last_error' => null,
                    ]
                );

                $updated++;

                if ($before !== $after && in_array($match->status, ['IN_PLAY', 'PAUSED'], true)) {
                    MatchDetailUpdated::dispatch($match);
                }
            } catch (Throwable $e) {
                FootballMatchDetail::updateOrCreate(
                    ['football_match_id' => $match->id],
                    [
                        'provider' => 'football_data',
                        'external_id' => (int) $match->external_id,
                        'last_error' => $e->getMessage(),
                    ]
                );
                $errors++;
            }
        }

        return ['selected' => $matches->count(), 'updated' => $updated, 'errors' => $errors];
    }

    /**
     * @return Collection<int, FootballMatch>
     */
    private function matchesToSync(int $limit): Collection
    {
        $staleMinutes = (int) config('football-data.match_details.stale_minutes', 15);

        return FootballMatch::query()
            ->where('stage', config('football-data.world_cup.stage'))
            ->whereBetween('utc_date', [now()->utc()->subHours(3), now()->utc()->addHours(24)])
            ->whereIn('status', ['TIMED', 'SCHEDULED', 'IN_PLAY', 'PAUSED', 'FINISHED'])
            ->leftJoin('football_match_details', 'football_match_details.football_match_id', '=', 'football_matches.id')
            ->where(function ($q) use ($staleMinutes): void {
                $q->whereNull('football_match_details.fetched_at')
                    ->orWhere('football_match_details.fetched_at', '<', now()->subMinutes($staleMinutes));
            })
            ->orderByRaw("case football_matches.status when 'IN_PLAY' then 0 when 'PAUSED' then 1 when 'TIMED' then 2 when 'SCHEDULED' then 3 else 4 end")
            ->orderBy('football_matches.utc_date')
            ->select('football_matches.*')
            ->limit(max(1, $limit))
            ->get();
    }
}
