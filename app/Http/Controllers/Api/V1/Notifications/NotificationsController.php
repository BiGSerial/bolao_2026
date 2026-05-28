<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'unread_only' => ['nullable', 'boolean'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $user = $request->user();

        $query = DatabaseNotification::query()
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at');

        if ((bool) ($validated['unread_only'] ?? false)) {
            $query->whereNull('read_at');
        }

        $page = $query->paginate($perPage)->withQueryString();

        return ApiResponse::success(
            request: $request,
            data: [
                'items' => $page->getCollection()->map(fn (DatabaseNotification $item): array => [
                    'id' => (string) $item->id,
                    'type' => (string) $item->type,
                    'data' => (array) $item->data,
                    'read_at' => optional($item->read_at)?->toIso8601String(),
                    'created_at' => optional($item->created_at)?->toIso8601String(),
                ])->values()->all(),
            ],
            meta: [
                'pagination' => [
                    'page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'total_pages' => $page->lastPage(),
                ],
            ],
        );
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = DatabaseNotification::query()
            ->where('id', $id)
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', $user->id)
            ->first();

        if (! $notification) {
            return ApiResponse::error($request, 'NOTIFICATION_NOT_FOUND', 'Notificação não encontrada.', 404);
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
            $notification->refresh();
        }

        return ApiResponse::success($request, [
            'id' => (string) $notification->id,
            'read_at' => optional($notification->read_at)?->toIso8601String(),
        ]);
    }
}
