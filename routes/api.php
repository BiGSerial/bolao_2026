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
use Illuminate\Support\Facades\Route;

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
            return ApiResponse::success($request, [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ]);
        });

        Route::get('/me/legal/pending', LegalPendingController::class);
        Route::get('/dashboard', DashboardController::class);
        Route::get('/matches', [MatchesController::class, 'index']);
        Route::get('/matches/{match}', [MatchesController::class, 'show']);
        Route::get('/pools', [PoolsController::class, 'index']);
        Route::post('/pools', [PoolsController::class, 'store']);
        Route::get('/pools/{pool}', [PoolsController::class, 'show']);
        Route::patch('/pools/{pool}', [PoolsController::class, 'update']);
        Route::delete('/pools/{pool}', [PoolsController::class, 'destroy']);
        Route::post('/pools/{pool}/join-requests', [PoolMembershipActionsController::class, 'joinRequest']);
        Route::post('/pools/join-by-code', [PoolMembershipActionsController::class, 'joinByCode']);
        Route::post('/pools/{pool}/leave', [PoolMembershipActionsController::class, 'leave']);
        Route::post('/pools/{pool}/invites', [PoolMembershipActionsController::class, 'invite']);
        Route::post('/pools/invites/{token}/accept', [PoolMembershipActionsController::class, 'acceptInvite']);
        Route::get('/pools/{pool}/members', [PoolMembersController::class, 'index']);
        Route::patch('/pools/{pool}/members/{member}', [PoolMembersController::class, 'update']);
        Route::delete('/pools/{pool}/members/{member}', [PoolMembersController::class, 'destroy']);
        Route::get('/pools/{pool}/predictions/me', [MyPredictionController::class, 'indexByPool']);
        Route::get('/pools/{pool}/matches/{match}/predictions/me', [MyPredictionController::class, 'show']);
        Route::put('/pools/{pool}/matches/{match}/predictions/me', [MyPredictionController::class, 'update']);
        Route::get('/pools/{pool}/rankings', [PoolRankingsController::class, 'index']);
        Route::get('/pools/{pool}/rankings/live', [PoolRankingsController::class, 'live']);
        Route::get('/standings', [StandingsController::class, 'index']);
        Route::get('/notifications', [NotificationsController::class, 'index']);
        Route::patch('/notifications/{id}/read', [NotificationsController::class, 'markAsRead']);
    });
});
