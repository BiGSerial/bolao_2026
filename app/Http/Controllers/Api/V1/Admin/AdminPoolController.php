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
            'status' => ['nullable', 'string', 'in:active,suspended,deleted'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Pool::query()->with(['owner:id,name', 'competition:id,name']);

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
                'owner' => $p->owner?->name,
                'competition' => $p->competition?->name,
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
        $pool->load(['owner:id,name,email', 'competition:id,name', 'members.user:id,name,email,status']);

        return ApiResponse::success($request, [
            'id' => $pool->id,
            'name' => $pool->name,
            'status' => $pool->status,
            'owner' => $pool->owner,
            'competition' => $pool->competition?->name,
            'members' => $pool->members->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role,
                'status' => $m->status,
            ]),
            'created_at' => $pool->created_at->toIso8601String(),
        ]);
    }

    public function updateStatus(Request $request, Pool $pool): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        $pool->update(['status' => $validated['status']]);

        return ApiResponse::success($request, [
            'id' => $pool->id,
            'status' => $pool->status,
        ], "Status do grupo {$pool->name} atualizado para {$pool->status}.");
    }
}
