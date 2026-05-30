<template>
    <div class="pwa-shell" @touchstart="onShellTouchStart" @touchmove="onShellTouchMove" @touchend="onShellTouchEnd">

        <!-- Header -->
        <header class="pwa-header">
            <!-- Left: back or logo -->
            <div class="pwa-header-side">
                <button
                    v-if="showBack"
                    class="flex items-center gap-1 text-bolao-accent font-semibold text-sm px-1 py-1 -ml-1 rounded-lg active:bg-white/5 transition-colors"
                    @click="goBack"
                >
                    <i class="ti ti-arrow-left text-lg leading-none"></i>
                    <span class="text-xs font-bold">Voltar</span>
                </button>
                <template v-else>
                    <img :src="'/favicon.png'" class="h-7 w-7 rounded-lg shrink-0 object-cover" alt="">
                    <span class="font-bc font-extrabold text-[17px] leading-none text-white ml-2 hidden sm:inline">
                        Bolão<span class="text-bolao-accent">VF</span>
                    </span>
                </template>
            </div>

            <!-- Center: Competition Switcher (Native feel) -->
            <div class="flex-1 flex justify-center px-2 min-w-0">
                <div v-if="!showBack && appStore.competitions.length" class="comp-switcher">
                    <select 
                        :value="appStore.selectedCompetitionId" 
                        @change="appStore.setCompetition(Number($event.target.value))"
                        class="comp-select-native"
                    >
                        <option v-for="c in appStore.competitions" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                    <div class="comp-display">
                        <span class="comp-name truncate">{{ currentCompetitionName }}</span>
                        <i class="ti ti-chevron-down text-[10px] ml-1 opacity-70"></i>
                    </div>
                </div>
                <p v-else-if="title" class="font-bold text-sm text-slate-100 truncate text-center leading-snug">
                    {{ title }}
                </p>
            </div>

            <!-- Right: avatar -->
            <div class="pwa-header-side justify-end">
                <div
                    class="bolao-avatar w-8 h-8 text-xs cursor-pointer active:scale-95 transition-transform"
                    @click="router.push('/pwa/profile')"
                >{{ initials }}</div>
            </div>
        </header>

        <!-- Main scrollable area -->
        <main
            class="pwa-main"
            ref="mainEl"
        >
            <RouterView v-slot="{ Component }">
                <Transition :name="transitionName" mode="out-in">
                    <component :is="Component" @set-title="setTitle" />
                </Transition>
            </RouterView>
        </main>

        <!-- Tab bar -->
        <nav class="pwa-tabbar">
            <button
                v-for="tab in visibleTabs"
                :key="tab.name"
                class="pwa-tab"
                :class="{ active: isTabActive(tab) }"
                @click="navigateTo(tab)"
            >
                <div class="pwa-tab-icon-wrap">
                    <i class="ti pwa-tab-icon leading-none" :class="isTabActive(tab) ? tab.iconFill : tab.icon"></i>
                </div>
                <span class="pwa-tab-label">{{ tab.label }}</span>
            </button>
        </nav>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../store/auth';
import { useAppStore } from '../../store/app';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const appStore = useAppStore();

const title = ref('');
const transitionName = ref('fade-page');

const tabs = [
    { name: 'dashboard', path: '/pwa/dashboard', label: 'Início',  icon: 'ti-home',          iconFill: 'ti-home' },
    { name: 'matches',   path: '/pwa/matches',   label: 'Jogos',   icon: 'ti-ball-football', iconFill: 'ti-ball-football' },
    { name: 'pools',     path: '/pwa/pools',     label: 'Bolões',  icon: 'ti-trophy',        iconFill: 'ti-trophy' },
    { name: 'management', path: '/pwa/management', label: 'Gestão', icon: 'ti-layout-dashboard', iconFill: 'ti-layout-dashboard', requiresManager: true },
    { name: 'admin',      path: '/pwa/admin',      label: 'Admin',  icon: 'ti-shield',        iconFill: 'ti-shield',           requiresAdmin: true },
    { name: 'profile',   path: '/pwa/profile',   label: 'Perfil',  icon: 'ti-user-circle',   iconFill: 'ti-user-circle' },
];

const visibleTabs = computed(() => {
    return tabs.filter(tab => {
        if (tab.requiresAdmin && !auth.isAdmin) return false;
        if (tab.requiresManager && !auth.isManager && !auth.isAdmin) return false;
        return true;
    });
});

const currentCompetitionName = computed(() => {
    const comp = appStore.competitions.find(c => c.id === appStore.selectedCompetitionId);
    return comp ? comp.name : 'Selecione';
});

const initials = computed(() => {
    const base =
        String(auth.user?.name || auth.user?.login || auth.user?.email || '')
            .replace(/@.*$/, '')
            .trim();
    if (!base) return '?';
    return base
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0].toUpperCase())
        .slice(0, 2)
        .join('');
});

onMounted(async () => {
    if (auth.isAuthenticated && !auth.user) {
        try {
            await auth.fetchMe();
        } catch {
            // Ignore
        }
    }
    appStore.fetchCompetitions();
});

const showBack = computed(() => ['pool-detail', 'pool-create', 'pool-match-detail', 'match-detail'].includes(route.name) || route.path.includes('/admin/') || route.path.includes('/management/'));

function setTitle(t) { title.value = t; }

function isTabActive(tab) {
    if (tab.name === 'pools') return route.path.startsWith('/pwa/pools');
    if (tab.name === 'management') return route.path.startsWith('/pwa/management');
    if (tab.name === 'admin') return route.path.startsWith('/pwa/admin');
    return route.path === tab.path;
}

function currentTabIndex() {
    for (let i = 0; i < visibleTabs.value.length; i++) {
        if (isTabActive(visibleTabs.value[i])) return i;
    }
    return -1;
}

function navigateTo(tab) {
    const currIdx = currentTabIndex();
    const nextIdx = visibleTabs.value.indexOf(tab);
    if (currIdx !== -1 && nextIdx !== -1) {
        transitionName.value = nextIdx > currIdx ? 'slide-left' : 'slide-right';
    } else {
        transitionName.value = 'fade-page';
    }
    router.push(tab.path);
}

function goBack() {
    transitionName.value = 'slide-right';
    router.back();
}

// ── Gesture handling (Swipe to go back/forward or tab switch) ──
let touchStartX = 0;
let touchStartY = 0;

function onShellTouchStart(e) {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
}

function onShellTouchMove(e) {
    // Basic swipe detection could go here if needed for visual feedback
}

function onShellTouchEnd(e) {
    const dx = e.changedTouches[0].clientX - touchStartX;
    const dy = e.changedTouches[0].clientY - touchStartY;

    // Horizontal swipe must be significantly larger than vertical
    if (Math.abs(dx) > 100 && Math.abs(dy) < 60) {
        if (dx > 0) {
            // Swipe right: Go back or previous tab
            if (showBack.value) {
                goBack();
            } else {
                const currIdx = currentTabIndex();
                if (currIdx > 0) navigateTo(visibleTabs.value[currIdx - 1]);
            }
        } else {
            // Swipe left: Next tab
            const currIdx = currentTabIndex();
            if (currIdx !== -1 && currIdx < visibleTabs.value.length - 1) {
                navigateTo(visibleTabs.value[currIdx + 1]);
            }
        }
    }
}

</script>

<style scoped>
.pwa-shell {
    display: flex;
    flex-direction: column;
    height: 100dvh;
    background: #0d0f12;
    overflow: hidden;
    user-select: none;
}

.pwa-header {
    display: flex;
    align-items: center;
    height: 56px;
    padding: 0 14px;
    background: #0d0f12;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-shrink: 0;
    z-index: 20;
    gap: 8px;
}

.pwa-header-side {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 60px;
    flex-shrink: 0;
}

.pwa-main {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
    background: #0d0f12;
}

/* ── Competition Switcher ── */
.comp-switcher {
    position: relative;
    max-width: 180px;
    width: 100%;
    display: flex;
    justify-content: center;
}

.comp-select-native {
    position: absolute;
    inset: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
    background: #1e293b;
    color: #f1f5f9;
}

.comp-select-native option {
    background: #1e293b;
    color: #f1f5f9;
    font-weight: 600;
}

.comp-display {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.05);
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.1);
    max-width: 100%;
}

.comp-name {
    font-size: 13px;
    font-weight: 700;
    color: #f1f5f9;
}

/* ── Tab bar ── */
.pwa-tabbar {
    display: flex;
    align-items: flex-start;
    padding-top: 8px;
    height: calc(68px + env(safe-area-inset-bottom, 0px));
    padding-bottom: env(safe-area-inset-bottom, 0px);
    background: #13161b;
    border-top: 1px solid rgba(245,166,35,0.3);
    flex-shrink: 0;
    z-index: 20;
}

.pwa-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 4px 2px 0;
    background: none;
    border: none;
    color: #64748b;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.15s, transform 0.1s;
    -webkit-tap-highlight-color: transparent;
}

.pwa-tab:active {
    transform: scale(0.92);
}

.pwa-tab.active { color: #f5a623; }

.pwa-tab-icon-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pwa-tab-icon {
    font-size: 22px;
}

.pwa-tab-label {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1;
}

/* ── Page transitions ── */
.fade-page-enter-active,
.fade-page-leave-active {
    transition: opacity 0.16s ease;
}
.fade-page-enter-from, .fade-page-leave-to { opacity: 0; }

.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
    transition: opacity 0.22s ease, transform 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
}
.slide-left-enter-from  { opacity: 0; transform: translateX(30px); }
.slide-left-leave-to    { opacity: 0; transform: translateX(-30px); }
.slide-right-enter-from { opacity: 0; transform: translateX(-30px); }
.slide-right-leave-to   { opacity: 0; transform: translateX(30px); }
</style>
