<?php

namespace App\Http\Controllers\Api\V1\Pools;

use App\Events\PoolChatMessageCreated;
use App\Events\PoolChatReadUpdated;
use App\Events\PoolChatReactionChanged;
use App\Events\PoolChatTypingChanged;
use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Models\PoolChatMessage;
use App\Models\PoolChatMessageEdit;
use App\Models\PoolChatMessageReaction;
use App\Models\PoolChatRead;
use App\Models\PoolMember;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PoolChatController extends Controller
{
    private const EDIT_WINDOW_MINUTES = 15;

    public function index(Request $request, Pool $pool): JsonResponse
    {
        $isAdmin = $this->isAdmin($request);

        if (! $this->canAccessChat($pool, (int) $request->user()->id, $isAdmin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        $limit = min(60, max(10, (int) $request->query('limit', 30)));
        $beforeId = (int) $request->query('before_id', 0);

        $query = PoolChatMessage::query()
            ->withTrashed()
            ->where('pool_id', $pool->id)
            ->with([
                'user:id,name,display_name',
                'replyTo:id,body,user_id,deleted_at',
                'replyTo.user:id,name,display_name',
                'reactions',
                'edits.editor:id,name,display_name',
                'deletedBy:id,name,display_name',
            ])
            ->orderByDesc('id')
            ->limit($limit);

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $items = $query->get()->reverse()->values();

        $read = PoolChatRead::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $request->user()->id)
            ->first();
        $readsMap = PoolChatRead::query()
            ->where('pool_id', $pool->id)
            ->whereNotNull('last_read_message_id')
            ->get(['user_id', 'last_read_message_id'])
            ->mapWithKeys(fn (PoolChatRead $row) => [(string) $row->user_id => (int) $row->last_read_message_id])
            ->all();

        return ApiResponse::success($request, [
            'items' => $items->map(fn (PoolChatMessage $msg): array => $this->serializeMessage($msg, $isAdmin))->values()->all(),
            'next_before_id' => $items->isNotEmpty() ? $items->first()->id : null,
            'read' => [
                'last_read_message_id' => (int) ($read?->last_read_message_id ?? 0),
                'read_at' => optional($read?->read_at)?->toIso8601String(),
            ],
            'reads_map' => $readsMap,
        ]);
    }

    public function store(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessChat($pool, (int) $request->user()->id, $this->isAdmin($request))) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'reply_to_message_id' => ['nullable', 'integer'],
        ]);

        $replyTo = null;
        if (! empty($validated['reply_to_message_id'])) {
            $replyTo = PoolChatMessage::query()
                ->where('pool_id', $pool->id)
                ->find((int) $validated['reply_to_message_id']);
            if (! $replyTo) {
                return ApiResponse::error($request, 'CHAT_REPLY_NOT_FOUND', 'Mensagem de resposta não encontrada.', 404);
            }
        }

        $message = PoolChatMessage::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $request->user()->id,
            'body' => trim((string) $validated['body']),
            'reply_to_message_id' => $replyTo?->id,
            'mentioned_user_ids' => $this->extractMentionedUserIds($pool, trim((string) $validated['body'])),
        ]);

        $message->load(['user:id,name,display_name', 'replyTo:id,body,user_id', 'replyTo.user:id,name,display_name', 'reactions']);
        PoolChatMessageCreated::dispatch($message);

        return ApiResponse::success($request, [
            'message' => $this->serializeMessage($message),
        ], 201);
    }

    public function update(Request $request, Pool $pool, PoolChatMessage $message): JsonResponse
    {
        $isAdmin = $this->isAdmin($request);

        if ((int) $message->pool_id !== (int) $pool->id) {
            return ApiResponse::error($request, 'CHAT_MESSAGE_NOT_FOUND', 'Mensagem não encontrada neste bolão.', 404);
        }

        if (! $this->canAccessChat($pool, (int) $request->user()->id, $isAdmin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        if ((int) $message->user_id !== (int) $request->user()->id) {
            return ApiResponse::error($request, 'CHAT_FORBIDDEN', 'Você não pode editar esta mensagem.', 403);
        }

        if ($message->trashed()) {
            return ApiResponse::error($request, 'CHAT_MESSAGE_DELETED', 'Mensagem já removida.', 422);
        }

        if (! $this->canEditMessage($message)) {
            return ApiResponse::error($request, 'CHAT_EDIT_WINDOW_EXPIRED', 'Você só pode editar uma mensagem até 15 minutos depois de enviá-la.', 409);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $body = trim((string) $validated['body']);
        if ($body === '') {
            return ApiResponse::error($request, 'CHAT_INVALID_BODY', 'Mensagem inválida.', 422);
        }

        if ($body === (string) $message->body) {
            return ApiResponse::success($request, [
                'message' => $this->serializeMessage($message->loadMissing(['user:id,name,display_name', 'replyTo:id,body,user_id', 'replyTo.user:id,name,display_name', 'reactions', 'edits.editor:id,name,display_name', 'deletedBy:id,name,display_name']), $isAdmin),
            ]);
        }

        PoolChatMessageEdit::query()->create([
            'message_id' => $message->id,
            'edited_by' => $request->user()->id,
            'old_body' => (string) $message->body,
            'new_body' => $body,
            'edited_at' => now(),
        ]);

        $message->forceFill([
            'body' => $body,
            'edited_at' => now(),
            'mentioned_user_ids' => $this->extractMentionedUserIds($pool, $body),
        ])->save();

        $message->load(['user:id,name,display_name', 'replyTo:id,body,user_id', 'replyTo.user:id,name,display_name', 'reactions', 'edits.editor:id,name,display_name', 'deletedBy:id,name,display_name']);
        PoolChatMessageCreated::dispatch($message);

        return ApiResponse::success($request, [
            'message' => $this->serializeMessage($message, $isAdmin),
        ]);
    }

    public function destroy(Request $request, Pool $pool, PoolChatMessage $message): JsonResponse
    {
        $isAdmin = $this->isAdmin($request);

        if ((int) $message->pool_id !== (int) $pool->id) {
            return ApiResponse::error($request, 'CHAT_MESSAGE_NOT_FOUND', 'Mensagem não encontrada neste bolão.', 404);
        }

        if (! $this->canAccessChat($pool, (int) $request->user()->id, $isAdmin)) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        if ((int) $message->user_id !== (int) $request->user()->id && ! $isAdmin) {
            return ApiResponse::error($request, 'CHAT_FORBIDDEN', 'Você não pode remover esta mensagem.', 403);
        }

        if (! $message->trashed()) {
            $message->forceFill(['deleted_by' => $request->user()->id])->save();
            $message->delete();
        }

        $message = PoolChatMessage::withTrashed()->find($message->id);
        $message?->load(['user:id,name,display_name', 'replyTo:id,body,user_id', 'replyTo.user:id,name,display_name', 'reactions']);
        if ($message) {
            PoolChatMessageCreated::dispatch($message);
        }

        return ApiResponse::success($request, ['ok' => true]);
    }

    public function react(Request $request, Pool $pool, PoolChatMessage $message): JsonResponse
    {
        if ((int) $message->pool_id !== (int) $pool->id) {
            return ApiResponse::error($request, 'CHAT_MESSAGE_NOT_FOUND', 'Mensagem não encontrada neste bolão.', 404);
        }

        if (! $this->canAccessChat($pool, (int) $request->user()->id, $this->isAdmin($request))) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'min:1', 'max:32'],
        ]);

        $emoji = trim((string) $validated['emoji']);

        $existing = PoolChatMessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $request->user()->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
            $state = 'removed';
        } else {
            PoolChatMessageReaction::query()->create([
                'message_id' => $message->id,
                'user_id' => $request->user()->id,
                'emoji' => $emoji,
            ]);
            $state = 'added';
        }

        $message->refresh();
        PoolChatReactionChanged::dispatch($message);

        return ApiResponse::success($request, [
            'message_id' => $message->id,
            'emoji' => $emoji,
            'state' => $state,
        ]);
    }

    public function typing(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessChat($pool, (int) $request->user()->id, $this->isAdmin($request))) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        $validated = $request->validate([
            'typing' => ['required', 'boolean'],
        ]);

        $typing = (bool) $validated['typing'];
        $cacheKey = sprintf('chat:typing:%d:%d', $pool->id, $request->user()->id);

        if ($typing) {
            Cache::put($cacheKey, 1, now()->addSeconds(6));
        } else {
            Cache::forget($cacheKey);
        }

        PoolChatTypingChanged::dispatch(
            (int) $pool->id,
            (int) $request->user()->id,
            (string) $request->user()->public_name,
            $typing,
        );

        return ApiResponse::success($request, ['ok' => true]);
    }

    public function markRead(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessChat($pool, (int) $request->user()->id, $this->isAdmin($request))) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        $validated = $request->validate([
            'last_read_message_id' => ['required', 'integer', 'min:1'],
        ]);

        $lastReadMessageId = (int) $validated['last_read_message_id'];
        $existsInPool = PoolChatMessage::query()
            ->where('pool_id', $pool->id)
            ->where('id', $lastReadMessageId)
            ->exists();

        if (! $existsInPool) {
            return ApiResponse::error($request, 'CHAT_MESSAGE_NOT_FOUND', 'Mensagem não encontrada neste bolão.', 404);
        }

        $readAt = now();
        PoolChatRead::query()->updateOrCreate(
            ['pool_id' => $pool->id, 'user_id' => $request->user()->id],
            ['last_read_message_id' => $lastReadMessageId, 'read_at' => $readAt]
        );
        PoolChatReadUpdated::dispatch((int) $pool->id, (int) $request->user()->id, $lastReadMessageId, $readAt->toIso8601String());

        return ApiResponse::success($request, [
            'last_read_message_id' => $lastReadMessageId,
            'read_at' => $readAt->toIso8601String(),
        ]);
    }

    public function participants(Request $request, Pool $pool): JsonResponse
    {
        if (! $this->canAccessChat($pool, (int) $request->user()->id, $this->isAdmin($request))) {
            return ApiResponse::error($request, 'POOL_FORBIDDEN', 'Acesso negado ao chat deste bolão.', 403);
        }

        $items = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('status', 'active')
            ->with('user:id,name,display_name')
            ->orderBy('id')
            ->get()
            ->map(fn (PoolMember $member): array => [
                'id' => (int) ($member->user?->id ?? 0),
                'name' => (string) ($member->user?->public_name ?? '—'),
            ])
            ->filter(fn (array $row) => $row['id'] > 0)
            ->values()
            ->all();

        return ApiResponse::success($request, ['items' => $items]);
    }

    private function canAccessChat(Pool $pool, int $userId, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        return $pool->members()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function serializeMessage(PoolChatMessage $msg, bool $isAdmin = false): array
    {
        $isDeleted = $msg->trashed() || $msg->deleted_at !== null;
        $body = $isDeleted && ! $isAdmin ? 'Mensagem apagada' : (string) $msg->body;
        $payload = [
            'id' => $msg->id,
            'pool_id' => $msg->pool_id,
            'body' => $body,
            'mentioned_user_ids' => (array) ($msg->mentioned_user_ids ?? []),
            'created_at' => optional($msg->created_at)?->toIso8601String(),
            'edited_at' => optional($msg->edited_at)?->toIso8601String(),
            'deleted_at' => optional($msg->deleted_at)?->toIso8601String(),
            'edit_expires_at' => optional($msg->created_at?->copy()->addMinutes(self::EDIT_WINDOW_MINUTES))?->toIso8601String(),
            'can_edit' => ! $isDeleted && $this->canEditMessage($msg),
            'user' => [
                'id' => $msg->user?->id,
                'name' => $msg->user?->public_name,
            ],
            'reply_to' => $msg->replyTo ? [
                'id' => $msg->replyTo->id,
                'body' => $msg->replyTo->body,
                'user_name' => $msg->replyTo->user?->public_name,
                'deleted_at' => optional($msg->replyTo->deleted_at)?->toIso8601String(),
            ] : null,
            'reactions' => $msg->reactions
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => $emoji,
                    'count' => $group->count(),
                    'user_ids' => $group->pluck('user_id')->values()->all(),
                ])
                ->values()
                ->all(),
        ];

        if ($isAdmin) {
            $payload['audit'] = [
                'deleted_body' => $isDeleted ? (string) $msg->body : null,
                'deleted_by' => $msg->deletedBy ? [
                    'id' => $msg->deletedBy->id,
                    'name' => $msg->deletedBy->public_name,
                ] : null,
                'edits' => $msg->edits
                    ->sortBy('edited_at')
                    ->map(fn (PoolChatMessageEdit $edit): array => [
                        'old_body' => $edit->old_body,
                        'new_body' => $edit->new_body,
                        'edited_at' => optional($edit->edited_at)?->toIso8601String(),
                        'edited_by' => $edit->editor ? [
                            'id' => $edit->editor->id,
                            'name' => $edit->editor->public_name,
                        ] : null,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $payload;
    }

    private function canEditMessage(PoolChatMessage $message): bool
    {
        if (! $message->created_at) {
            return false;
        }

        return $message->created_at->greaterThanOrEqualTo(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ((bool) $user->is_admin || $user->can('admin'));
    }

    private function extractMentionedUserIds(Pool $pool, string $body): array
    {
        if ($body === '') {
            return [];
        }

        preg_match_all('/@([\\p{L}0-9_\\.\\-]{2,40})/u', $body, $matches);
        $tokens = collect($matches[1] ?? [])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $members = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('status', 'active')
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($members->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $members)
            ->get(['id', 'name', 'display_name']);

        $mentioned = [];
        foreach ($users as $user) {
            $candidates = collect([
                mb_strtolower((string) $user->public_name),
                mb_strtolower((string) $user->name),
            ])->filter()->unique();

            if ($candidates->some(fn ($name) => $tokens->contains($name))) {
                $mentioned[] = (int) $user->id;
            }
        }

        return array_values(array_unique($mentioned));
    }
}
