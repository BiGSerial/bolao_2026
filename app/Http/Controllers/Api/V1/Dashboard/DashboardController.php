<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enums\PoolMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\Prediction;
use App\Support\Api\MatchPayload;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'competition_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $competitionId = isset($validated['competition_id']) ? (int) $validated['competition_id'] : null;

        $liveStatuses = ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'];
        $upcomingStatuses = ['TIMED', 'SCHEDULED', 'PRE_MATCH'];

        $liveMatchesQuery = FootballMatch::query()
            ->with([
                'competition:id,code,name',
                'homeTeam:id,name,canonical_name_br,short_name,tla,crest',
                'awayTeam:id,name,canonical_name_br,short_name,tla,crest',
                'detail:id,football_match_id,payload',
            ])
            ->whereIn('status', $liveStatuses)
            ->orderByDesc('utc_date');
        if ($competitionId) {
            $liveMatchesQuery->where('competition_id', $competitionId);
        }
        $liveMatches = $liveMatchesQuery->limit(5)->get();

        $upcomingMatchesQuery = FootballMatch::query()
            ->with(['competition:id,code,name', 'homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest'])
            ->whereIn('status', $upcomingStatuses)
            ->where('utc_date', '>=', now()->utc())
            ->orderBy('utc_date');
        if ($competitionId) {
            $upcomingMatchesQuery->where('competition_id', $competitionId);
        }
        $upcomingMatches = $upcomingMatchesQuery->limit(10)->get();

        $poolsQuery = Pool::query()
            ->with('competition:id,code,name')
            ->where('status', 'active')
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->whereIn('status', [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value]);
            })
            ->orderBy('name');
        if ($competitionId) {
            $poolsQuery->where('competition_id', $competitionId);
        }
        $pools = $poolsQuery->get();

        $pendingPredictionsCount = $this->pendingPredictionsCount((int) $user->id);

        return ApiResponse::success($request, [
            'summary' => [
                'live_matches_count' => $liveMatches->count(),
                'upcoming_matches_count' => $upcomingMatches->count(),
                'pools_count' => $pools->count(),
                'pending_predictions_count' => $pendingPredictionsCount,
            ],
            'live_matches' => $liveMatches->map(fn (FootballMatch $match): array => MatchPayload::fromModel($match))->values()->all(),
            'upcoming_matches' => $upcomingMatches->map(fn (FootballMatch $match): array => MatchPayload::fromModel($match))->values()->all(),
            'pools' => $pools->map(fn (Pool $pool): array => [
                'id' => $pool->id,
                'name' => $pool->name,
                'slug' => $pool->slug,
                'status' => $pool->status,
                'competition' => [
                    'id' => $pool->competition?->id,
                    'code' => $pool->competition?->code,
                    'name' => $pool->competition?->name,
                ],
            ])->values()->all(),
        ]);
    }

    private function pendingPredictionsCount(int $userId): int
    {
        $nowUtc = now()->utc();

        $activePools = Pool::query()
            ->where('status', 'active')
            ->whereHas('members', function ($query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->where('status', PoolMemberStatus::Active->value);
            })
            ->get(['id', 'competition_id', 'competition_season_id', 'prediction_lock_minutes']);

        $count = 0;

        foreach ($activePools as $pool) {
            if (! $pool->competition_id || ! $pool->competition_season_id) {
                continue;
            }

            $candidateMatches = FootballMatch::query()
                ->where('competition_id', $pool->competition_id)
                ->where('competition_season_id', $pool->competition_season_id)
                ->whereIn('status', ['TIMED', 'SCHEDULED', 'PRE_MATCH', 'IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
                ->where('utc_date', '>=', $nowUtc->copy()->subDay())
                ->get(['id', 'utc_date', 'status']);

            if ($candidateMatches->isEmpty()) {
                continue;
            }

            $predictedMatchIds = Prediction::query()
                ->where('pool_id', $pool->id)
                ->where('user_id', $userId)
                ->whereIn('football_match_id', $candidateMatches->pluck('id'))
                ->pluck('football_match_id')
                ->all();

            foreach ($candidateMatches as $match) {
                if (in_array($match->id, $predictedMatchIds, true)) {
                    continue;
                }

                if ($match->isPredictionLockedFor($pool)) {
                    continue;
                }

                $count++;
            }
        }

        return $count;
    }
}
