<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\UserModerationController;
use App\Livewire\Admin\ApiSyncDashboard;
use App\Livewire\Admin\ManualMatchCorrection;
use App\Livewire\Admin\UsersApproval;
use App\Livewire\Dashboard\Home;
use App\Livewire\Auth\ForcePasswordChange;
use App\Livewire\Management\MyPoolsManager;
use App\Livewire\Pools\PoolCreate;
use App\Livewire\Pools\PoolIndex;
use App\Livewire\Pools\PoolMembers;
use App\Livewire\Pools\PoolMatchShow;
use App\Livewire\Pools\PoolSettings;
use App\Livewire\Pools\PoolShow;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/trocar-senha-obrigatoria', ForcePasswordChange::class)->name('password.force');
});

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/dashboard', Home::class)->name('dashboard');
    Route::get('/boloes', PoolIndex::class)->name('pools.index');
    Route::get('/boloes/criar', PoolCreate::class)->name('pools.create');
    Route::get('/gerenciar', MyPoolsManager::class)->name('management.pools');
    Route::get('/boloes/{pool:slug}', PoolShow::class)->name('pools.show');
    Route::get('/boloes/{pool:slug}/jogos/{match}', PoolMatchShow::class)->name('pools.matches.show');
    Route::get('/boloes/{pool:slug}/membros', PoolMembers::class)->name('pools.members');
    Route::get('/boloes/{pool:slug}/configuracoes', PoolSettings::class)->name('pools.settings');
});

Route::middleware(['auth', 'password.changed', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/usuarios', UsersApproval::class)->name('users.approval');
    Route::post('/usuarios/{user}/aprovar', [UserModerationController::class, 'approve'])->name('users.approve');
    Route::post('/usuarios/{user}/rejeitar', [UserModerationController::class, 'reject'])->name('users.reject');
    Route::post('/usuarios/{user}/suspender', [UserModerationController::class, 'suspend'])->name('users.suspend');
    Route::get('/api-sync', ApiSyncDashboard::class)->name('api.sync');
    Route::get('/jogos/correcao-manual', ManualMatchCorrection::class)->name('matches.manual-correction');
});

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
