<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->can('admin') || abort(403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,suspended,blocked,archived,deleted'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Pool::query()->with(['owner:id,name', 'competition:id,name,code']);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderBy('name');

        $pools = $query->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success($request, [
            'items' => $pools->getCollection()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status,
                'suspension_reason' => $p->suspension_reason,
                'owner' => $p->owner?->name,
                'competition' => $p->competition?->name,
                'competition_code' => $p->competition?->code,
                'members_count' => $p->members()->count(),
                'created_at' => $p->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'total' => $pools->total(),
                'per_page' => $pools->perPage(),
                'current_page' => $pools->currentPage(),
                'last_page' => $pools->lastPage(),
            ]
        ]);
    }

    public function show(Request $request, Pool $pool): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        $pool->load(['owner:id,name,email', 'competition:id,name,code', 'members.user:id,name,email,status']);

        return ApiResponse::success($request, [
            'id' => $pool->id,
            'name' => $pool->name,
            'description' => $pool->description,
            'instructions' => $pool->instructions,
            'visibility' => $pool->visibility,
            'status' => $pool->status,
            'suspension_reason' => $pool->suspension_reason,
            'invite_code' => $pool->invite_code,
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
            'sectors' => is_array($pool->sectors) ? array_values($pool->sectors) : [],
            'tie_breakers' => is_array($pool->tie_breakers) ? array_values($pool->tie_breakers) : [],
            'owner' => $pool->owner,
            'competition' => $pool->competition,
            'members' => $pool->members->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role,
                'status' => $m->status,
                'sector' => $m->sector,
            ]),
            'created_at' => $pool->created_at->toIso8601String(),
        ]);
    }

    public function update(Request $request, Pool $pool): JsonResponse
    {
        $request->user()->can('admin') || abort(403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'visibility' => ['required', 'in:private,invite_only,public'],
            'status' => ['required', 'in:active,suspended,blocked,archived'],
            'suspension_reason' => ['nullable', 'string', 'max:2000'],
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
            'suspension_reason' => $data['status'] === 'suspended'
                ? (trim((string) ($data['suspension_reason'] ?? '')) ?: 'Suspenso pelo administrador.')
                : null,
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

        return $this->show($request, $pool->fresh());
    }

    public function updateStatus(Request $request, Pool $pool): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended'],
            'suspension_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $pool->update([
            'status' => $validated['status'],
            'suspension_reason' => $validated['status'] === 'suspended'
                ? (trim((string) ($validated['suspension_reason'] ?? '')) ?: 'Suspenso pelo administrador.')
                : null,
        ]);

        return ApiResponse::success($request, [
            'id' => $pool->id,
            'status' => $pool->status,
            'suspension_reason' => $pool->suspension_reason,
        ], "Status do grupo {$pool->name} atualizado para {$pool->status}.");
    }
}
