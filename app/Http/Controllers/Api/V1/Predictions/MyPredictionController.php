<?php

namespace App\Http\Controllers\Api\V1\Predictions;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\Prediction;
use App\Models\User;
use App\Services\Predictions\PredictionService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyPredictionController extends Controller
{
    public function __construct(
        private readonly PredictionService $predictionService,
    ) {}

    public function show(Request $request, Pool $pool, FootballMatch $match): JsonResponse
    {
        if (! $this->canAccessPool($request, $pool)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        if ((int) $pool->competition_id !== (int) $match->competition_id || (int) $pool->competition_season_id !== (int) $match->competition_season_id) {
            return ApiResponse::error($request, 'MATCH_OUT_OF_POOL_SCOPE', 'Jogo fora do escopo do bolão.', 404);
        }

        $prediction = Prediction::query()
            ->where('pool_id', $pool->id)
            ->where('football_match_id', $match->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $poolClosedLockAt = $pool->closedPredictionsLockTime();
        $poolClosedLocked = $pool->areClosedPredictionsLocked();
        $matchLocked = $match->isPredictionLockedFor($pool);

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'match_id' => $match->id,
            'prediction' => $prediction ? [
                'home_score' => $prediction->home_score,
                'away_score' => $prediction->away_score,
                'last_changed_at' => optional($prediction->last_changed_at)?->toIso8601String(),
            ] : null,
            'lock' => [
                'is_locked' => $matchLocked || $poolClosedLocked,
                'lock_at' => ($pool->closed_predictions ? $poolClosedLockAt : $match->predictionLockTimeFor($pool))
                    ?->timezone(config('app.timezone'))
                    ->toIso8601String(),
                'allow_prediction_changes' => (bool) $pool->allow_prediction_changes,
                'closed_predictions' => (bool) $pool->closed_predictions,
            ],
        ]);
    }

    public function update(Request $request, Pool $pool, FootballMatch $match): JsonResponse
    {
        if (! $this->canAccessPool($request, $pool)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        if ((int) $pool->competition_id !== (int) $match->competition_id || (int) $pool->competition_season_id !== (int) $match->competition_season_id) {
            return ApiResponse::error($request, 'MATCH_OUT_OF_POOL_SCOPE', 'Jogo fora do escopo do bolão.', 404);
        }

        $validated = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'away_score' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        try {
            $prediction = $this->predictionService->save(
                pool: $pool,
                match: $match,
                user: $request->user(),
                homeScore: (int) $validated['home_score'],
                awayScore: (int) $validated['away_score'],
            );
        } catch (DomainException $exception) {
            return ApiResponse::error(
                request: $request,
                code: 'PREDICTION_RULE_VIOLATION',
                message: $exception->getMessage(),
                status: 409,
            );
        }

        return ApiResponse::success($request, [
            'pool_id' => $pool->id,
            'match_id' => $match->id,
            'prediction' => [
                'home_score' => $prediction->home_score,
                'away_score' => $prediction->away_score,
                'last_changed_at' => optional($prediction->last_changed_at)?->toIso8601String(),
            ],
        ]);
    }

    public function indexByPool(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessPool($request, $pool)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        $validated = $request->validate([
            'status'    => ['nullable', 'string', 'max:40'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],
            'open_only' => ['nullable', 'boolean'],
            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $userId  = (int) $request->user()->id;

        $matchesQuery = FootballMatch::query()
            ->with(['homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest'])
            ->where('competition_id', $pool->competition_id)
            ->where('competition_season_id', $pool->competition_season_id)
            ->where('stage', $pool->stage)
            ->orderBy('utc_date');

        if (! empty($validated['status'])) {
            $matchesQuery->where('status', strtoupper((string) $validated['status']));
        }
        if (! empty($validated['date_from'])) {
            // Interpreta a data como fuso Brasil e converte para UTC para capturar jogos de 23h BRT corretamente.
            $dateFromUtc = \Carbon\Carbon::parse($validated['date_from'], 'America/Sao_Paulo')->startOfDay()->utc();
            $matchesQuery->where('utc_date', '>=', $dateFromUtc->format('Y-m-d H:i:s'));
        }
        if (! empty($validated['date_to'])) {
            $dateToUtc = \Carbon\Carbon::parse($validated['date_to'], 'America/Sao_Paulo')->endOfDay()->utc();
            $matchesQuery->where('utc_date', '<=', $dateToUtc->format('Y-m-d H:i:s'));
        }
        // Filtra apenas jogos que ainda podem receber palpite (exclui encerrados/cancelados)
        if ($request->boolean('open_only')) {
            $matchesQuery->whereNotIn('status', ['FINISHED', 'AWARDED', 'CANCELLED', 'POSTPONED', 'SUSPENDED']);
        }

        $matchesPage = $matchesQuery->paginate($perPage)->withQueryString();
        $matchIds = $matchesPage->getCollection()->pluck('id');

        $predictionsByMatch = Prediction::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $userId)
            ->whereIn('football_match_id', $matchIds)
            ->get(['football_match_id', 'home_score', 'away_score', 'last_changed_at'])
            ->keyBy('football_match_id');

        $poolClosedLockAt = $pool->closedPredictionsLockTime();
        $poolClosedLocked = $pool->areClosedPredictionsLocked();

        $items = $matchesPage->getCollection()->map(function (FootballMatch $match) use ($pool, $predictionsByMatch, $poolClosedLocked, $poolClosedLockAt): array {
            $prediction = $predictionsByMatch->get($match->id);

            return [
                'match' => [
                    'id' => $match->id,
                    'status' => (string) $match->status,
                    'utc_date' => optional($match->utc_date)?->toIso8601String(),
                    'local_date' => optional($match->kickoffAtBrazil())?->toIso8601String(),
                    'stage' => $match->stage,
                    'matchday' => $match->matchday,
                    'home_team' => [
                        'id' => $match->homeTeam?->id,
                        'name' => $match->homeTeam?->localized_name,
                        'tla' => $match->homeTeam?->tla,
                        'crest' => $match->homeTeam?->crest,
                    ],
                    'away_team' => [
                        'id' => $match->awayTeam?->id,
                        'name' => $match->awayTeam?->localized_name,
                        'tla' => $match->awayTeam?->tla,
                        'crest' => $match->awayTeam?->crest,
                    ],
                    'score' => [
                        'home' => $match->home_score_full_time,
                        'away' => $match->away_score_full_time,
                    ],
                ],
                'prediction' => $prediction ? [
                    'home_score' => $prediction->home_score,
                    'away_score' => $prediction->away_score,
                    'last_changed_at' => optional($prediction->last_changed_at)?->toIso8601String(),
                    'points' => $this->calculatePerMatchPoints(
                        pool: $pool,
                        predictionHome: (int) $prediction->home_score,
                        predictionAway: (int) $prediction->away_score,
                        homeReal: is_numeric($match->home_score_full_time) ? (int) $match->home_score_full_time : null,
                        awayReal: is_numeric($match->away_score_full_time) ? (int) $match->away_score_full_time : null,
                    ),
                ] : null,
                'lock' => [
                    'is_locked' => $match->isPredictionLockedFor($pool) || $poolClosedLocked,
                    'lock_at' => ($pool->closed_predictions ? $poolClosedLockAt : $match->predictionLockTimeFor($pool))
                        ?->timezone(config('app.timezone'))
                        ->toIso8601String(),
                    'closed_predictions' => (bool) $pool->closed_predictions,
                ],
            ];
        })->values()->all();

        return ApiResponse::success(
            request: $request,
            data: [
                'pool_id' => $pool->id,
                'items' => $items,
            ],
            meta: [
                'pagination' => [
                    'page' => $matchesPage->currentPage(),
                    'per_page' => $matchesPage->perPage(),
                    'total' => $matchesPage->total(),
                    'total_pages' => $matchesPage->lastPage(),
                ],
            ],
        );
    }

    public function indexByUser(Request $request, Pool $pool, User $user): JsonResponse
    {
        if (! $this->canAccessPool($request, $pool)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        $targetMembership = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $targetMembership) {
            return ApiResponse::error($request, 'POOL_MEMBER_NOT_FOUND', 'Participante não encontrado neste bolão.', 404);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 200);
        $poolClosedLockAt = $pool->closedPredictionsLockTime();
        $poolClosedLocked = $pool->areClosedPredictionsLocked();

        $matchesQuery = FootballMatch::query()
            ->with(['homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest'])
            ->where('competition_id', $pool->competition_id)
            ->where('competition_season_id', $pool->competition_season_id)
            ->where('stage', $pool->stage)
            ->orderBy('utc_date');

        if (! $poolClosedLocked) {
            $matchesQuery->where(function ($query) use ($pool): void {
                $query
                    ->whereIn('status', ['FINISHED', 'AWARDED'])
                    ->orWhere('utc_date', '<=', now()->utc()->addMinutes((int) $pool->predictionLockMinutes()));
            });
        }

        $matchesPage = $matchesQuery->paginate($perPage)->withQueryString();
        $matchIds = $matchesPage->getCollection()->pluck('id');

        $predictionsByMatch = Prediction::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $user->id)
            ->whereIn('football_match_id', $matchIds)
            ->get(['football_match_id', 'home_score', 'away_score', 'last_changed_at'])
            ->keyBy('football_match_id');

        $items = $matchesPage->getCollection()->map(function (FootballMatch $match) use ($pool, $predictionsByMatch, $poolClosedLocked, $poolClosedLockAt): array {
            $prediction = $predictionsByMatch->get($match->id);

            return [
                'match' => [
                    'id' => $match->id,
                    'status' => (string) $match->status,
                    'utc_date' => optional($match->utc_date)?->toIso8601String(),
                    'local_date' => optional($match->kickoffAtBrazil())?->toIso8601String(),
                    'stage' => $match->stage,
                    'matchday' => $match->matchday,
                    'home_team' => [
                        'id' => $match->homeTeam?->id,
                        'name' => $match->homeTeam?->localized_name,
                        'tla' => $match->homeTeam?->tla,
                        'crest' => $match->homeTeam?->crest,
                    ],
                    'away_team' => [
                        'id' => $match->awayTeam?->id,
                        'name' => $match->awayTeam?->localized_name,
                        'tla' => $match->awayTeam?->tla,
                        'crest' => $match->awayTeam?->crest,
                    ],
                    'score' => [
                        'home' => $match->home_score_full_time,
                        'away' => $match->away_score_full_time,
                    ],
                ],
                'prediction' => $prediction ? [
                    'home_score' => $prediction->home_score,
                    'away_score' => $prediction->away_score,
                    'last_changed_at' => optional($prediction->last_changed_at)?->toIso8601String(),
                    'points' => $this->calculatePerMatchPoints(
                        pool: $pool,
                        predictionHome: (int) $prediction->home_score,
                        predictionAway: (int) $prediction->away_score,
                        homeReal: is_numeric($match->home_score_full_time) ? (int) $match->home_score_full_time : null,
                        awayReal: is_numeric($match->away_score_full_time) ? (int) $match->away_score_full_time : null,
                    ),
                ] : null,
                'lock' => [
                    'is_locked' => $match->isPredictionLockedFor($pool) || $poolClosedLocked,
                    'lock_at' => ($pool->closed_predictions ? $poolClosedLockAt : $match->predictionLockTimeFor($pool))
                        ?->timezone(config('app.timezone'))
                        ->toIso8601String(),
                    'closed_predictions' => (bool) $pool->closed_predictions,
                ],
            ];
        })->values()->all();

        return ApiResponse::success(
            request: $request,
            data: [
                'pool_id' => $pool->id,
                'user' => [
                    'id' => (int) $user->id,
                    'name' => (string) ($user->public_name ?? $user->display_name ?? $user->name),
                ],
                'items' => $items,
                'visibility' => [
                    'all_matches' => (bool) $poolClosedLocked,
                    'reason' => $poolClosedLocked ? 'closed_predictions_locked' : 'locked_or_finished_only',
                ],
            ],
            meta: [
                'pagination' => [
                    'page' => $matchesPage->currentPage(),
                    'per_page' => $matchesPage->perPage(),
                    'total' => $matchesPage->total(),
                    'total_pages' => $matchesPage->lastPage(),
                ],
            ],
        );
    }

    private function canAccessPool(Request $request, Pool $pool): bool
    {
        return $pool->members()
            ->where('user_id', $request->user()->id)
            ->exists();
    }

    private function calculatePerMatchPoints(
        Pool $pool,
        int $predictionHome,
        int $predictionAway,
        ?int $homeReal,
        ?int $awayReal,
    ): ?int {
        if ($homeReal === null || $awayReal === null) {
            return null;
        }

        $exactScorePoints = max(0, (int) ($pool->points_exact_score ?? 5));
        $correctResultPoints = max(0, (int) ($pool->points_correct_result ?? 3));
        $correctGoalsPoints = max(0, (int) ($pool->points_correct_goals ?? 1));

        if ($predictionHome === $homeReal && $predictionAway === $awayReal) {
            return $exactScorePoints;
        }

        $points = 0;
        if ($this->resultOf($homeReal, $awayReal) === $this->resultOf($predictionHome, $predictionAway)) {
            $points += $correctResultPoints;
        }

        $hitHomeGoals = $predictionHome === $homeReal;
        $hitAwayGoals = $predictionAway === $awayReal;
        $correctGoalsMode = (string) ($pool->correct_goals_mode ?? 'both_teams');

        if ($correctGoalsMode === 'winner_only') {
            if ($homeReal > $awayReal && $hitHomeGoals) {
                $points += $correctGoalsPoints;
            } elseif ($awayReal > $homeReal && $hitAwayGoals) {
                $points += $correctGoalsPoints;
            }
        } elseif ($hitHomeGoals || $hitAwayGoals) {
            $points += $correctGoalsPoints;
        }

        return $points;
    }

    private function resultOf(int $home, int $away): string
    {
        return $home > $away ? 'H' : ($home < $away ? 'A' : 'D');
    }
}
