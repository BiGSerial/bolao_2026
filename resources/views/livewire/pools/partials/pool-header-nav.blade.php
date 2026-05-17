@props([
    'pool',
    'activeItem' => 'jogos',
    'memberStatus' => 'active',
    'memberRole' => 'member',
    'myRanking' => null,
    'showBulkAction' => false,
    'showInstructionsToggle' => false,
])

<div class="pool-header-root sticky top-0 z-20">
    <div class="pool-header-shell">
        <div class="pool-title-row">
            <div class="pool-name-group">
                <a href="{{ route('pools.index') }}" class="pool-back-btn">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div class="min-w-0">
                    <p class="pool-competition-title">Bolão</p>
                    <h1 class="pool-name">{{ $pool->name }}</h1>
                    <p class="pool-status-dot">
                        <span>{{ in_array(strtolower((string) $memberStatus), ['active', 'ativo'], true) ? 'Bolão ativo' : 'Bolão inativo' }} · {{ ucfirst((string) $memberStatus) }}</span>
                        @if($pool->instructions)
                            @if($showInstructionsToggle)
                            <button wire:click="$toggle('showInstructions')" class="pool-status-instructions">• Instruções</button>
                            @else
                            <span class="pool-status-instructions">• Instruções</span>
                            @endif
                        @endif
                    </p>
                </div>
            </div>

            @if($myRanking)
            <div class="pool-stats-chips">
                <div class="pool-stat-chip">
                    <span class="pool-stat-value">#{{ $myRanking->position ?? '—' }}</span>
                    <span class="pool-stat-label">Posição</span>
                </div>
                <div class="pool-stat-chip">
                    <span class="pool-stat-value pool-stat-value-points">{{ $myRanking->points_total }}</span>
                    <span class="pool-stat-label">Pts</span>
                </div>
            </div>
            @endif
        </div>

    </div>
</div>

@once
<style>
    .pool-header-root { border-bottom: 1px solid rgba(255, 255, 255, 0.07); background: #13161b; backdrop-filter: blur(6px); }
    .pool-header-shell { padding: 0.35rem 0.75rem 0.3rem; }
    .pool-title-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .pool-name-group { display: flex; align-items: center; gap: 0.55rem; min-width: 0; flex: 1 1 auto; }
    .pool-back-btn { width: 1.7rem; height: 1.7rem; border-radius: 0.4rem; border: 1px solid #2e2e2e; background: #171717; color: #999; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.15s; }
    .pool-back-btn:hover { color: #e8e8e0; border-color: #444; background: #202020; }
    .pool-competition-title { color: #7a8394; font-size: 0.62rem; font-weight: 600; line-height: 1.1; margin-bottom: 0.08rem; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pool-name { color: #e8e8e0; font-size: 0.98rem; font-weight: 800; line-height: 1.02; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pool-status-dot { display: flex; align-items: center; gap: 0.35rem; color: #4caf50; font-size: 0.6rem; font-weight: 500; }
    .pool-status-dot::before { content: ''; width: 0.34rem; height: 0.34rem; border-radius: 9999px; background: #4caf50; flex-shrink: 0; }
    .pool-status-instructions { color: #666; transition: color 0.15s; }
    .pool-status-instructions:hover { color: #aaa; }
    .pool-stats-chips { display: flex; gap: 0.45rem; flex-shrink: 0; }
    .pool-stat-chip { display: flex; flex-direction: column; align-items: center; background: #232323; border: 1px solid #2e2e2e; border-radius: 0.45rem; padding: 0.3rem 0.55rem; min-width: 3.1rem; }
    .pool-stat-label { font-size: 0.52rem; color: #666; font-weight: 600; line-height: 1; margin-top: 0.1rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .pool-stat-value { font-size: 1rem; font-weight: 900; color: #f5a623; line-height: 1; }
    .pool-stat-value-points { color: #e8e8e0; }

    .pool-tabs-desktop { display: flex; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; padding-bottom: 0.1rem; }
    .pool-tabs-desktop::-webkit-scrollbar { display: none; }
    .pool-header-root .pool-tabs-desktop { border-color: #2a2a2a; }
    .pool-header-root .tab-btn { display: inline-flex; align-items: center; gap: 0.38rem; padding: 0.6rem 1.1rem 0.58rem; color: #666; font-size: 13px; font-weight: 500; border-bottom: 2px solid transparent; white-space: nowrap; flex: 0 0 auto; }
    .pool-header-root .tab-btn:hover { color: #aaa; }
    .pool-header-root .tab-btn.active { color: #f5a623; border-bottom-color: #f5a623; }
    .pool-header-root .tab-btn::after { display: none !important; content: none !important; }
    .pool-header-root .tab-btn-right { margin-left: 1.25rem; }
    .tab-ico { width: 14px; height: 14px; opacity: 0.95; flex-shrink: 0; }

    .pool-tabs-bar { align-items: center; }
    .pool-bulk-top-btn { display: inline-flex; align-items: center; gap: 0.45rem; height: 2rem; padding: 0 0.9rem; border-radius: 0.45rem; border: 1px solid rgba(255, 255, 255, 0.09); background: transparent; color: #a4adbc; font-size: 0.8rem; font-weight: 600; transition: all 0.15s; white-space: nowrap; }
    .pool-bulk-top-btn:hover { color: #e8e8e0; border-color: rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.03); }

    @media (min-width: 640px) {
        .pool-header-shell { padding: 0.55rem 1.5rem 0.5rem; }
        .pool-back-btn { width: 1.9rem; height: 1.9rem; }
        .pool-name { font-size: 1.35rem; }
        .pool-competition-title { font-size: 0.68rem; }
        .pool-status-dot { font-size: 0.72rem; }
        .pool-stat-chip { min-width: 3.65rem; padding: 0.35rem 0.8rem; }
        .pool-stat-label { font-size: 0.62rem; }
        .pool-stat-value { font-size: 1.2rem; }
    }
    @media (max-width: 639px) {
        .pool-title-row { align-items: center; flex-wrap: nowrap; row-gap: 0; }
        .pool-name-group { min-width: 0; width: auto; flex: 1 1 auto; }
        .pool-stats-chips { margin-left: auto; }
        .pool-stat-chip { min-width: 2.8rem; padding: 0.24rem 0.45rem; }
        .pool-stat-value { font-size: 0.92rem; }
        .pool-stat-label { font-size: 0.48rem; }
        .pool-bulk-top-btn { display: none; }
        .pool-header-root .tab-btn { padding: 0.58rem 0.72rem 0.56rem; gap: 0; }
        .pool-header-root .tab-btn .tab-label { display: none; }
        .pool-header-root .tab-btn-right { margin-left: 0.55rem; }
        .pool-header-root .tab-ico { width: 15px; height: 15px; }
    }
</style>
@endonce
