<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Admin\UserModerationController;
use App\Http\Controllers\Admin\LegalAuditExportController;
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
use App\Livewire\Matches\MatchShow;
use App\Livewire\Pools\PoolCreate;
use App\Livewire\Pools\PoolIndex;
use App\Livewire\Pools\PoolMembers;
use App\Livewire\Pools\PoolMatchShow;
use App\Livewire\Pools\PoolSettings;
use App\Livewire\Pools\PoolShow;
use App\Models\FootballMatch;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;


Route::get('/health', function () {
    $checks = [
        'app' => true,
        'database' => false,
        'redis' => false,
    ];

    try {
        DB::select('select 1');
        $checks['database'] = true;
    } catch (\Throwable $e) {
        $checks['database'] = false;
    }

    try {
        Redis::connection()->ping();
        $checks['redis'] = true;
    } catch (\Throwable $e) {
        $checks['redis'] = false;
    }

    $ok = collect($checks)->every(fn ($check) => $check === true);

    return response()->json([
        'status' => $ok ? 'ok' : 'error',
        'version' => env('APP_VERSION'),
        'checks' => $checks,
        'time' => now()->toIso8601String(),
    ], $ok ? 200 : 503);
})->name('health');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $avatarColors = [
        'linear-gradient(135deg,#f5a623,#e8390d)',
        'linear-gradient(135deg,#3b82f6,#6366f1)',
        'linear-gradient(135deg,#22c55e,#16a34a)',
        'linear-gradient(135deg,#a855f7,#7c3aed)',
        'linear-gradient(135deg,#f43f5e,#e11d48)',
    ];

    $avatarUsers = \App\Models\User::query()
        ->where('is_admin', false)
        ->inRandomOrder()
        ->limit(5)
        ->get(['id', 'name', 'display_name'])
        ->map(function ($user, $index) use ($avatarColors) {
            $sourceName = trim((string) ($user->display_name ?: $user->name));
            $words = preg_split('/\s+/u', $sourceName) ?: [];
            $initials = mb_strtoupper(
                implode('', array_map(fn ($w) => mb_substr((string) $w, 0, 1), array_slice($words, 0, 2)))
            ) ?: '?';

            return [
                'initials' => $initials,
                'color' => $avatarColors[$index % count($avatarColors)],
            ];
        });

    $participantCount = \App\Models\User::query()
        ->where('is_admin', false)
        ->count();

    $availablePredictionMatches = FootballMatch::query()
        ->where('status', '!=', 'FINISHED')
        ->count();

    $liveStatuses = ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'];
    $upcomingStatuses = ['TIMED', 'SCHEDULED', 'PRE_MATCH'];

    $heroMatch = FootballMatch::query()
        ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
        ->whereIn('status', $liveStatuses)
        ->orderByDesc('utc_date')
        ->first();

    if (! $heroMatch) {
        $heroMatch = FootballMatch::query()
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->whereIn('status', $upcomingStatuses)
            ->where('utc_date', '>=', now()->utc())
            ->orderBy('utc_date')
            ->first();
    }

    $heroMatchData = null;
    if ($heroMatch) {
        $isLive = in_array($heroMatch->status, $liveStatuses, true);
        $kickoff = ($heroMatch->local_date ?? $heroMatch->utc_date)?->timezone(config('app.timezone'));

        $heroMatchData = [
            'is_live' => $isLive,
            'badge' => $heroMatch->stage ?: 'Partida',
            'when' => $isLive ? 'Ao vivo' : ($kickoff?->format('d/m · H\hi') ?? 'Em breve'),
            'home_name' => $heroMatch->homeTeam?->localized_name ?? ($heroMatch->homeTeam?->short_name ?? 'Seleção A'),
            'away_name' => $heroMatch->awayTeam?->localized_name ?? ($heroMatch->awayTeam?->short_name ?? 'Seleção B'),
            'home_crest' => $heroMatch->homeTeam?->crest,
            'away_crest' => $heroMatch->awayTeam?->crest,
            'home_score' => $heroMatch->home_score_full_time,
            'away_score' => $heroMatch->away_score_full_time,
        ];
    }

    $liveCompetitions = FootballMatch::query()
        ->whereIn('status', array_merge($liveStatuses, ['PRE_MATCH']))
        ->with('competition:id,name')
        ->orderByDesc('utc_date')
        ->get()
        ->pluck('competition.name')
        ->filter()
        ->unique()
        ->values();

    return view('landing', compact('avatarUsers', 'participantCount', 'heroMatchData', 'liveCompetitions', 'availablePredictionMatches'));
})->name('landing');

Route::get('/legal/eula', [LegalPageController::class, 'eula'])->name('legal.eula');
Route::get('/legal/privacy-policy', [LegalPageController::class, 'privacyPolicy'])->name('legal.privacy-policy');
Route::get('/legal/terms', [LegalPageController::class, 'terms'])->name('legal.terms');
Route::get('/legal/disclaimer', [LegalPageController::class, 'disclaimer'])->name('legal.disclaimer');
Route::get('/legal/confidentiality-policy', [LegalPageController::class, 'confidentialityPolicy'])->name('legal.confidentiality-policy');
Route::get('/sobre', [LegalPageController::class, 'about'])->name('about');

Route::middleware('guest')->group(function (): void {
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::middleware(['auth', 'user.active'])->group(function (): void {
    Route::get('/trocar-senha-obrigatoria', ForcePasswordChange::class)->name('password.force');
});

Route::middleware(['auth', 'user.active', 'password.changed', 'legal.accepted'])->group(function (): void {
    Route::get('/dashboard', Home::class)->name('dashboard');
    Route::get('/jogos/{match}', MatchShow::class)->name('matches.show');
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
    Route::get('/legal/exports/download', [LegalAuditExportController::class, 'download'])->name('legal.exports.download');
});

Route::middleware(['auth', 'user.active', 'password.changed', 'legal.accepted'])->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
