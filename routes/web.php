<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\UserModerationController;
use App\Http\Controllers\Legal\LegalAcceptanceController;
use App\Http\Controllers\Pools\PoolInviteController;
use App\Http\Controllers\Legal\LegalPageController;
use App\Livewire\Admin\ApiSyncDashboard;
use App\Livewire\Admin\LegalDocumentsManager;
use App\Livewire\Admin\ManualMatchCorrection;
use App\Livewire\Admin\PoolsControl;
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

Route::get('/legal/eula', [LegalPageController::class, 'eula'])->name('legal.eula');
Route::get('/legal/privacy-policy', [LegalPageController::class, 'privacyPolicy'])->name('legal.privacy-policy');
Route::get('/legal/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('/sobre', [LegalPageController::class, 'about'])->name('about');

Route::middleware('guest')->group(function (): void {
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::middleware(['auth', 'user.active'])->group(function (): void {
    Route::get('/trocar-senha-obrigatoria', ForcePasswordChange::class)->name('password.force');
});

Route::middleware(['auth', 'user.active', 'password.changed', 'legal.accepted'])->group(function (): void {
    Route::get('/dashboard', Home::class)->name('dashboard');
    Route::get('/boloes', PoolIndex::class)->name('pools.index');
    Route::get('/boloes/criar', PoolCreate::class)->name('pools.create');
    Route::get('/gerenciar', MyPoolsManager::class)->name('management.pools');
    Route::get('/boloes/{pool:slug}', PoolShow::class)->name('pools.show');
    Route::get('/boloes/{pool:slug}/jogos/{match}', PoolMatchShow::class)->name('pools.matches.show');
    Route::get('/boloes/{pool:slug}/membros', PoolMembers::class)->name('pools.members');
    Route::get('/boloes/{pool:slug}/configuracoes', PoolSettings::class)->name('pools.settings');
});

Route::get('/convites/{token}', [PoolInviteController::class, 'accept'])->name('pools.invites.accept');

Route::middleware(['auth', 'user.active', 'password.changed'])->group(function (): void {
    Route::get('/legal/acceptance', [LegalAcceptanceController::class, 'show'])->name('legal.acceptance.show');
    Route::post('/legal/acceptance', [LegalAcceptanceController::class, 'store'])->name('legal.acceptance.store');
});

Route::middleware(['auth', 'user.active', 'password.changed', 'legal.accepted', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/usuarios', UsersApproval::class)->name('users.approval');
    Route::get('/grupos', PoolsControl::class)->name('pools.control');
    Route::post('/usuarios/{user}/aprovar', [UserModerationController::class, 'approve'])->name('users.approve');
    Route::post('/usuarios/{user}/rejeitar', [UserModerationController::class, 'reject'])->name('users.reject');
    Route::post('/usuarios/{user}/suspender', [UserModerationController::class, 'suspend'])->name('users.suspend');
    Route::get('/api-sync', ApiSyncDashboard::class)->name('api.sync');
    Route::get('/jogos/correcao-manual', ManualMatchCorrection::class)->name('matches.manual-correction');
    Route::get('/legal', LegalDocumentsManager::class)->name('legal.index');
});

Route::middleware(['auth', 'user.active', 'password.changed', 'legal.accepted'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
