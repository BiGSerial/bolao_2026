<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Enums\PoolMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use App\Models\PoolRanking;
use App\Models\User;
use App\Services\Pools\PoolFinalizationService;
use App\Services\Pools\PoolRankingService;
use App\Services\Predictions\PredictionScoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PoolsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $managedOnly = $request->boolean('managed');

        if ($managedOnly) {
            $ownedPools = Pool::query()
                ->with(['competition:id,code,name'])
                ->whereHas('members', function ($query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->where('role', 'owner')
                        ->whereIn('status', [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value]);
                })
                ->orderBy('name')
                ->get();

            return ApiResponse::success($request, [
                'items' => $ownedPools->map(function (Pool $pool): array {
                    $activeCount = (int) $pool->members()->where('status', PoolMemberStatus::Active->value)->count();
                    $pendingCount = (int) $pool->members()->where('status', PoolMemberStatus::Pending->value)->count();
                    $leader = PoolRanking::query()
                        ->where('pool_id', $pool->id)
                        ->where('position', 1)
                        ->with('user:id,name,display_name')
                        ->first();
                    $statsBase = PoolRanking::query()->where('pool_id', $pool->id);

                    return [
                        'id' => $pool->id,
                        'name' => $pool->name,
                        'status' => $pool->status,
                        'invite_code' => $pool->invite_code,
                        'competition' => [
                            'id' => $pool->competition?->id,
                            'code' => $pool->competition?->code,
                            'name' => $pool->competition?->name,
                        ],
                        'members_count' => $activeCount,
                        'pending_count' => $pendingCount,
                        'stats' => [
                            'leader_name' => $leader?->user?->public_name,
                            'leader_points' => (int) ($leader?->points_total ?? 0),
                            'points_distributed' => (int) $statsBase->sum('points_total'),
                            'predictions_counted' => (int) $statsBase->sum('predictions_counted'),
                        ],
                    ];
                })->values()->all(),
            ]);
        }

        $memberPools = Pool::query()
            ->with(['competition:id,code,name'])
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->whereIn('status', [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value]);
            })
            ->orderBy('name')
            ->get();

        $discoverablePools = Pool::query()
            ->with(['competition:id,code,name'])
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->whereDoesntHave('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return ApiResponse::success($request, [
            'member_pools' => $memberPools->map(fn (Pool $pool): array => $this->poolSummary($pool, $user->id))->values()->all(),
            'discoverable_pools' => $discoverablePools->map(fn (Pool $pool): array => $this->poolSummary($pool, $user->id))->values()->all(),
            'competitions' => collect($this->availableCompetitionOptions($user))
                ->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name])
                ->values()
                ->all(),
            'limits' => [
                'max_pools' => User::MAX_MANAGED_POOLS,
                'joined_pools' => $user->participatingPoolsCount(),
                'can_join_more' => ! $user->reachedParticipatingPoolsLimit(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $allowedCompetitionCodes = array_keys($this->availableCompetitionOptions($user));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'competition_code' => ['required', 'string', 'in:'.implode(',', $allowedCompetitionCodes)],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'visibility' => ['required', 'in:private,invite_only,public'],
            'allow_prediction_changes' => ['sometimes', 'boolean'],
            'closed_predictions' => ['sometimes', 'boolean'],
            'allow_pending_member_predictions' => ['sometimes', 'boolean'],
            'prediction_lock_minutes' => ['sometimes', 'integer', 'min:10'],
            'points_exact_score' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'points_correct_result' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'points_correct_goals' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'correct_goals_mode' => ['sometimes', 'in:both_teams,winner_only'],
            'sectors' => ['sometimes', 'array', 'max:30'],
            'sectors.*' => ['string', 'max:80'],
            'tie_breakers' => ['sometimes', 'array', 'max:5'],
            'tie_breakers.*' => ['string', 'in:exact_scores,correct_results,correct_home_goals,correct_away_goals,predictions_counted'],
        ]);

        $context = $this->resolveCompetitionContext((string) $data['competition_code']);
        if ($user->reachedParticipatingPoolsLimitByCompetition((int) $context['competition']->id)) {
            return ApiResponse::error($request, 'POOL_LIMIT_REACHED', 'Você já atingiu o limite de 5 bolões para este campeonato.', 409);
        }

        $userId = (int) $user->id;

        $pool = DB::transaction(function () use ($data, $context, $userId): Pool {
            $pool = Pool::query()->create([
                'owner_id' => $userId,
                'competition_id' => $context['competition']->id,
                'competition_season_id' => $context['season']->id,
                'name' => $data['name'],
                'slug' => $this->makeUniqueSlug((string) $data['name']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'instructions' => trim((string) ($data['instructions'] ?? '')) ?: null,
                'sectors' => !empty($data['sectors']) ? array_values($data['sectors']) : null,
                'tie_breakers' => !empty($data['tie_breakers']) ? array_values($data['tie_breakers']) : null,
                'visibility' => $data['visibility'],
                'status' => 'active',
                'invite_code' => $this->makeUniqueInviteCode(),
                'allow_prediction_changes' => (bool) ($data['allow_prediction_changes'] ?? true),
                'closed_predictions' => (bool) ($data['closed_predictions'] ?? false),
                'prediction_lock_minutes' => (int) ($data['prediction_lock_minutes'] ?? 10),
                'allow_pending_member_predictions' => (bool) ($data['allow_pending_member_predictions'] ?? true),
                'points_exact_score' => (int) ($data['points_exact_score'] ?? 5),
                'points_correct_result' => (int) ($data['points_correct_result'] ?? 3),
                'points_correct_goals' => (int) ($data['points_correct_goals'] ?? 1),
                'correct_goals_mode' => (string) ($data['correct_goals_mode'] ?? 'both_teams'),
                'stage' => $context['stage'],
            ]);

            $pool->members()->create([
                'user_id' => $userId,
                'role' => 'owner',
                'status' => 'active',
                'activated_by' => $userId,
                'activated_at' => now(),
            ]);

            return $pool;
        });

        $pool->loadMissing(['competition:id,code,name']);

        return ApiResponse::success($request, [
            'pool' => $this->poolSummary($pool, (int) $user->id),
        ], 201);
    }

    public function update(
        Request $request,
        Pool $pool,
        PoolRankingService $rankingService,
        PredictionScoringService $scoringService
    ): JsonResponse {
        $user = $request->user();
        $canManage = $this->canManagePool($pool, (int) $user->id, (bool) $user->is_admin);
        if (! $canManage) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado para configurar este bolão.', 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'visibility' => ['required', 'in:private,invite_only,public'],
            'status' => ['required', 'in:active,blocked,archived'],
            'allow_prediction_changes' => ['required', 'boolean'],
            'closed_predictions' => ['sometimes', 'boolean'],
            'allow_pending_member_predictions' => ['required', 'boolean'],
            'prediction_lock_minutes' => ['required', 'integer', 'min:10'],
            'points_exact_score' => ['required', 'integer', 'min:0', 'max:20'],
            'points_correct_result' => ['required', 'integer', 'min:0', 'max:20'],
            'points_correct_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'correct_goals_mode' => ['required', 'in:both_teams,winner_only'],
            'sectors' => ['nullable', 'array', 'max:30'],
            'sectors.*' => ['string', 'max:80'],
            'tie_breakers' => ['nullable', 'array', 'max:5'],
            'tie_breakers.*' => ['string', 'in:exact_scores,correct_results,correct_home_goals,correct_away_goals,predictions_counted'],
        ]);

        $pool->update([
            'name' => $data['name'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'instructions' => trim((string) ($data['instructions'] ?? '')) ?: null,
            'visibility' => $data['visibility'],
            'status' => $data['status'],
            'allow_prediction_changes' => (bool) $data['allow_prediction_changes'],
            'closed_predictions' => array_key_exists('closed_predictions', $data) ? (bool) $data['closed_predictions'] : (bool) $pool->closed_predictions,
            'prediction_lock_minutes' => (int) $data['prediction_lock_minutes'],
            'allow_pending_member_predictions' => (bool) $data['allow_pending_member_predictions'],
            'points_exact_score' => (int) $data['points_exact_score'],
            'points_correct_result' => (int) $data['points_correct_result'],
            'points_correct_goals' => (int) $data['points_correct_goals'],
            'correct_goals_mode' => (string) $data['correct_goals_mode'],
            'sectors' => ! empty($data['sectors']) ? array_values($data['sectors']) : null,
            'tie_breakers' => ! empty($data['tie_breakers']) ? array_values($data['tie_breakers']) : null,
        ]);

        $fresh = $pool->fresh();
        $scoringService->recalculatePool($fresh);
        $rankingService->recalculate($fresh);

        return ApiResponse::success($request, [
            'pool' => $this->poolSummary($fresh->loadMissing('competition:id,code,name'), (int) $user->id),
            'message' => 'Configurações salvas com sucesso.',
        ]);
    }

    public function show(Request $request, Pool $pool): JsonResponse
    {
        $user = $request->user();

        $membership = $pool->members()
            ->where('user_id', $user->id)
            ->first(['id', 'role', 'status', 'sector']);

        $isPublic = (string) $pool->visibility === 'public' && (string) $pool->status === 'active';
        $isAdmin = (bool) $user->is_admin;

        if (! $isAdmin && ! $membership && ! $isPublic) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao bolão.', 403);
        }

        $pool->loadMissing(['competition:id,code,name', 'owner:id,name,display_name']);
        $canManage = $this->canManagePool($pool, (int) $user->id, (bool) $user->is_admin);

        return ApiResponse::success($request, [
            'id' => $pool->id,
            'name' => $pool->name,
            'slug' => $pool->slug,
            'description' => $pool->description,
            'visibility' => $pool->visibility,
            'status' => $pool->status,
            'stage' => $pool->stage,
            'allow_prediction_changes' => (bool) $pool->allow_prediction_changes,
            'closed_predictions' => (bool) $pool->closed_predictions,
            'allow_pending_member_predictions' => (bool) $pool->allow_pending_member_predictions,
            'prediction_lock_minutes' => (int) $pool->prediction_lock_minutes,
            'scoring' => [
                'points_exact_score' => (int) ($pool->points_exact_score ?? 0),
                'points_correct_result' => (int) ($pool->points_correct_result ?? 0),
                'points_correct_goals' => (int) ($pool->points_correct_goals ?? 0),
                'correct_goals_mode' => (string) ($pool->correct_goals_mode ?? 'both_teams'),
            ],
            'competition' => [
                'id' => $pool->competition?->id,
                'code' => $pool->competition?->code,
                'name' => $pool->competition?->name,
            ],
            'sectors' => is_array($pool->sectors) ? array_values($pool->sectors) : [],
            'tie_breakers' => is_array($pool->tie_breakers) ? array_values($pool->tie_breakers) : [],
            'owner' => [
                'id' => $pool->owner?->id,
                'name' => $pool->owner?->public_name,
            ],
            'permissions' => [
                'can_manage' => $canManage,
            ],
            'membership' => $membership ? [
                'role' => $membership->role,
                'status' => $membership->status,
                'sector' => $membership->sector,
            ] : null,
        ]);
    }

    public function destroy(Request $request, Pool $pool): JsonResponse
    {
        $user = $request->user();
        $membership = $pool->members()
            ->where('user_id', $user->id)
            ->first(['role']);

        if (! $membership || $membership->role !== 'owner') {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Apenas o dono pode excluir o bolão.', 403);
        }

        $pool->delete();

        return ApiResponse::success($request, [
            'status' => 'deleted',
            'message' => 'Bolão excluído com sucesso.',
        ]);
    }

    public function finalize(
        Request $request,
        Pool $pool,
        PoolFinalizationService $finalizationService,
    ): JsonResponse {
        $user = $request->user();
        $membership = $pool->members()
            ->where('user_id', $user->id)
            ->first(['role']);

        if (! $membership || $membership->role !== 'owner') {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Apenas o dono pode finalizar o bolão.', 403);
        }

        if ((string) $pool->status === 'archived') {
            return ApiResponse::error($request, 'POOL_ALREADY_FINALIZED', 'Este bolão já está finalizado.', 409);
        }

        $result = $finalizationService->finalize($pool);

        return ApiResponse::success($request, [
            'status' => 'finalized',
            'pool_id' => $pool->id,
            'pool_status' => (string) $result['pool']->status,
            'notified_users' => (int) $result['notified_users'],
            'winner' => [
                'name' => $result['rankings']->first()?->user?->public_name,
                'points' => (int) ($result['rankings']->first()?->points_total ?? 0),
            ],
            'stats' => $result['stats'],
            'message' => 'Bolão finalizado com sucesso. Ranking consolidado e e-mails enviados.',
        ]);
    }

    private function poolSummary(Pool $pool, int $userId): array
    {
        $membership = $pool->members()
            ->where('user_id', $userId)
            ->first(['role', 'status']);

        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'slug' => $pool->slug,
            'visibility' => $pool->visibility,
            'status' => $pool->status,
            'invite_code' => $pool->invite_code,
            'competition' => [
                'id' => $pool->competition?->id,
                'code' => $pool->competition?->code,
                'name' => $pool->competition?->name,
            ],
            'membership' => $membership ? [
                'role' => $membership->role,
                'status' => $membership->status,
            ] : null,
        ];
    }

    private function canManagePool(Pool $pool, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        return $pool->members()
            ->where('user_id', $userId)
            ->whereIn('role', ['owner', 'manager'])
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    private function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'bolao';
        $i = 2;

        while (Pool::query()->where('slug', $slug)->exists()) {
            $slug = ($base !== '' ? $base : 'bolao').'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function makeUniqueInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Pool::query()->where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * @return array<string, string>
     */
    private function availableCompetitionOptions(User $user): array
    {
        $configured = (array) config('football-data.competitions', []);
        $options = [];

        foreach ($configured as $code => $settings) {
            $normalizedCode = strtoupper((string) $code);
            $enabled = (bool) data_get($settings, 'enabled', false);
            if (! $enabled && $normalizedCode !== 'WC' && !((bool) $user->is_admin)) {
                continue;
            }
            if (! $user->canAccessCompetition($normalizedCode)) {
                continue;
            }

            $label = (string) data_get($settings, 'name', $normalizedCode);
            $options[$normalizedCode] = $label !== '' ? $label : $normalizedCode;
        }

        if ($options === []) {
            $options['WC'] = 'Copa do Mundo';
        }

        return $options;
    }

    /**
     * @return array{competition: Competition, season: CompetitionSeason, stage: string}
     */
    private function resolveCompetitionContext(string $competitionCode): array
    {
        $code = strtoupper(trim($competitionCode));
        $competition = Competition::query()->where('code', $code)->first();
        if (! $competition) {
            abort(422, 'Competição inválida.');
        }

        $configuredSeason = (int) config('football-data.competitions.'.$code.'.season', 0);
        $season = CompetitionSeason::query()
            ->where('competition_id', $competition->id)
            ->where('year', $configuredSeason)
            ->latest('id')
            ->first();

        if (! $season) {
            abort(422, 'Temporada da competição ainda não sincronizada.');
        }

        $stage = (string) config('football-data.competitions.'.$code.'.default_stage', 'GROUP_STAGE');

        return [
            'competition' => $competition,
            'season' => $season,
            'stage' => $stage,
        ];
    }
}
