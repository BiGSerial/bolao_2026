<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:pending,active,suspended,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->where('id', '!=', Auth::id());

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('login', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } else {
            // Default sort: pending first
            $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END");
        }

        $query->orderBy('name');

        $users = $query->paginate($validated['per_page'] ?? 20);

        return ApiResponse::success($request, [
            'items' => $users->getCollection()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'login' => $u->login,
                'status' => $u->status,
                'is_admin' => (bool)$u->is_admin,
                'created_at' => $u->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        abort_if($user->id === Auth::id(), 403, 'Você não pode alterar seu próprio status.');

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,suspended,rejected'],
        ]);

        $user->update(['status' => $validated['status']]);

        return ApiResponse::success($request, [
            'id' => $user->id,
            'status' => $user->status,
            'message' => "Status do usuário {$user->name} atualizado para {$user->status}.",
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        $tempPassword = Str::random(10);
        
        $user->update([
            'password' => Hash::make($tempPassword),
            'temporary_password_expires_at' => now()->addHours(24),
        ]);

        return ApiResponse::success($request, [
            'temp_password' => $tempPassword,
            'message' => "Nova senha temporária gerada para {$user->name}.",
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $request->user()->can('admin') || abort(403);
        abort_if($user->id === Auth::id(), 403, 'Você não pode excluir sua própria conta.');
        
        $name = $user->name;
        $user->delete();

        return ApiResponse::success($request, [
            'deleted' => true,
            'message' => "Usuário {$name} removido permanentemente.",
        ]);
    }
}
