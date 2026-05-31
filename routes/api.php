<?php

use App\Http\Controllers\Api\V1\Auth\AuthTokenController;
use App\Http\Controllers\Api\V1\App\AppConfigController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Me\LegalPendingController;
use App\Http\Controllers\Api\V1\Matches\MatchesController;
use App\Http\Controllers\Api\V1\Notifications\NotificationsController;
use App\Http\Controllers\Api\V1\Pools\PoolsController;
use App\Http\Controllers\Api\V1\Pools\PoolMembershipActionsController;
use App\Http\Controllers\Api\V1\Pools\PoolMembersController;
use App\Http\Controllers\Api\V1\Predictions\MyPredictionController;
use App\Http\Controllers\Api\V1\Rankings\PoolRankingsController;
use App\Http\Controllers\Api\V1\Standings\StandingsController;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Broadcast auth para o PWA (Bearer token, sem CSRF).
// O Echo do PWA usa authEndpoint: '/broadcasting/auth'.
// Como o route group de API não tem CSRF, auth:sanctum funciona com Bearer token.
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return ApiResponse::success(request(), [
            'status' => 'ok',
            'time' => now()->toIso8601String(),
        ]);
    });

    Route::post('/auth/login', [AuthTokenController::class, 'login']);
    Route::get('/app-config', AppConfigController::class);
    Route::middleware('auth:sanctum')->post('/auth/logout', [AuthTokenController::class, 'logout']);
    Route::middleware('auth:sanctum')->post('/auth/refresh', [AuthTokenController::class, 'refresh']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', function (Request $request) {
            $user = $request->user();

            // Verifica documentos legais pendentes inline para evitar request extra no frontend.
            $legalPending = false;
            if (! $user->is_admin) {
                $required = \App\Models\LegalDocument::query()
                    ->active()
                    ->whereIn('type', [\App\Enums\LegalDocumentType::Eula->value, \App\Enums\LegalDocumentType::PrivacyPolicy->value])
                    ->orderByDesc('published_at')
                    ->get(['id', 'type'])
                    ->unique('type');

                if ($required->count() >= 2) {
                    $acceptedCount = \App\Models\UserLegalAcceptance::query()
                        ->where('user_id', $user->id)
                        ->whereIn('legal_document_id', $required->pluck('id'))
                        ->count();
                    $legalPending = $acceptedCount < 2;
                }
            }

            return ApiResponse::success($request, [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'is_admin'          => (bool) $user->is_admin,
                'is_manager'        => $user->poolMemberships()
                    ->whereIn('role', ['owner', 'manager'])
                    ->where('status', 'active')
                    ->exists(),
                'is_owner'          => $user->poolMemberships()
                    ->where('role', 'owner')
                    ->where('status', 'active')
                    ->exists(),
                'legal_pending'     => $legalPending,
            ]);
        });

        Route::patch('/me', App\Http\Controllers\Api\V1\Me\UpdateProfileController::class);
        Route::get('/me/legal/pending', LegalPendingController::class);
        Route::post('/me/legal/accept', App\Http\Controllers\Api\V1\Me\AcceptLegalController::class);
        Route::get('/legal/{type}', App\Http\Controllers\Api\V1\Legal\LegalDocumentApiController::class . '@show');
        Route::post('/feedback', App\Http\Controllers\Api\V1\Feedback\FeedbackController::class);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/matches', [MatchesController::class, 'index']);
        Route::get('/matches/{match}', [MatchesController::class, 'show']);
        Route::get('/matches/{match}/detail', App\Http\Controllers\Api\V1\Matches\MatchDetailController::class);
        Route::get('/pools', [PoolsController::class, 'index']);
        Route::post('/pools', [PoolsController::class, 'store']);
        Route::get('/pools/{pool}', [PoolsController::class, 'show']);
        Route::patch('/pools/{pool}', [PoolsController::class, 'update']);
        Route::delete('/pools/{pool}', [PoolsController::class, 'destroy']);
        Route::post('/pools/{pool}/finalize', [PoolsController::class, 'finalize']);
        Route::post('/pools/{pool}/join-requests', [PoolMembershipActionsController::class, 'joinRequest']);
        Route::post('/pools/join-by-code', [PoolMembershipActionsController::class, 'joinByCode']);
        Route::post('/pools/{pool}/leave', [PoolMembershipActionsController::class, 'leave']);
        Route::post('/pools/{pool}/invites', [PoolMembershipActionsController::class, 'invite']);
        Route::post('/pools/invites/{token}/accept', [PoolMembershipActionsController::class, 'acceptInvite']);
        Route::get('/pools/{pool}/members', [PoolMembersController::class, 'index']);
        Route::patch('/pools/{pool}/members/{member}', [PoolMembersController::class, 'update']);
        Route::delete('/pools/{pool}/members/{member}', [PoolMembersController::class, 'destroy']);
        Route::get('/pools/{pool}/chat/messages', [App\Http\Controllers\Api\V1\Pools\PoolChatController::class, 'index']);
        Route::get('/pools/{pool}/chat/participants', [App\Http\Controllers\Api\V1\Pools\PoolChatController::class, 'participants']);
        Route::post('/pools/{pool}/chat/messages', [App\Http\Controllers\Api\V1\Pools\PoolChatController::class, 'store']);
        Route::post('/pools/{pool}/chat/messages/{message}/reactions', [App\Http\Controllers\Api\V1\Pools\PoolChatController::class, 'react']);
        Route::post('/pools/{pool}/chat/typing', [App\Http\Controllers\Api\V1\Pools\PoolChatController::class, 'typing']);
        Route::post('/pools/{pool}/chat/read', [App\Http\Controllers\Api\V1\Pools\PoolChatController::class, 'markRead']);
        Route::get('/pools/{pool}/predictions/me', [MyPredictionController::class, 'indexByPool']);
        Route::get('/pools/{pool}/matches/{match}/predictions', App\Http\Controllers\Api\V1\Pools\PoolMatchPredictionsController::class);
        Route::get('/pools/{pool}/matches/{match}/predictions/me', [MyPredictionController::class, 'show']);
        Route::put('/pools/{pool}/matches/{match}/predictions/me', [MyPredictionController::class, 'update']);
        Route::get('/pools/{pool}/rankings', [PoolRankingsController::class, 'index']);
        Route::get('/pools/{pool}/rankings/live', [PoolRankingsController::class, 'live']);
        Route::get('/standings', [StandingsController::class, 'index']);
        Route::get('/notifications', [NotificationsController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationsController::class, 'markAsRead']);
        Route::post('/notifications/subscriptions', [App\Http\Controllers\Api\V1\Notifications\PushSubscriptionController::class, 'store']);
        Route::delete('/notifications/subscriptions', [App\Http\Controllers\Api\V1\Notifications\PushSubscriptionController::class, 'destroy']);

        // Admin
        Route::middleware('can:admin')->prefix('admin')->group(function (): void {
            Route::get('/users', [App\Http\Controllers\Api\V1\Admin\AdminUserModerationController::class, 'index']);
            Route::patch('/users/{user}/status', [App\Http\Controllers\Api\V1\Admin\AdminUserModerationController::class, 'updateStatus']);
            Route::post('/users/{user}/reset-password', [App\Http\Controllers\Api\V1\Admin\AdminUserModerationController::class, 'resetPassword']);
            Route::delete('/users/{user}', [App\Http\Controllers\Api\V1\Admin\AdminUserModerationController::class, 'destroy']);

            Route::get('/pools', [App\Http\Controllers\Api\V1\Admin\AdminPoolController::class, 'index']);
            Route::get('/pools/{pool}', [App\Http\Controllers\Api\V1\Admin\AdminPoolController::class, 'show']);
            Route::patch('/pools/{pool}', [App\Http\Controllers\Api\V1\Admin\AdminPoolController::class, 'update']);
            Route::patch('/pools/{pool}/status', [App\Http\Controllers\Api\V1\Admin\AdminPoolController::class, 'updateStatus']);
            Route::get('/sync/status', [App\Http\Controllers\Api\V1\Admin\AdminOpsController::class, 'syncStatus']);
            Route::post('/sync/run', [App\Http\Controllers\Api\V1\Admin\AdminOpsController::class, 'runSync']);
            Route::get('/emails/status', [App\Http\Controllers\Api\V1\Admin\AdminOpsController::class, 'emailStatus']);
            Route::post('/emails/sync', [App\Http\Controllers\Api\V1\Admin\AdminOpsController::class, 'runEmailSync']);
        });
    });
});
