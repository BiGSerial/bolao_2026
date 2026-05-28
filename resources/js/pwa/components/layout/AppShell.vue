<template>
    <div class="pwa-shell">

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
                    <span class="font-bc font-extrabold text-[17px] leading-none text-white">
                        Bolão<span class="text-bolao-accent">VF</span>
                    </span>
                </template>
            </div>

            <!-- Center: title -->
            <div class="flex-1 flex justify-center px-2 min-w-0">
                <p v-if="title" class="font-bold text-sm text-slate-100 truncate text-center leading-snug">
                    {{ title }}
                </p>
            </div>

            <!-- Right: avatar -->
            <div class="pwa-header-side justify-end">
                <div
                    class="bolao-avatar w-8 h-8 text-xs"
                    @click="router.push('/app/profile')"
                >{{ initials }}</div>
            </div>
        </header>

        <!-- Main scrollable area with swipe gesture -->
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
                v-for="tab in tabs"
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

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const title = ref('');
const transitionName = ref('fade-page');

const tabs = [
    { name: 'dashboard', path: '/app/dashboard', label: 'Início',  icon: 'ti-home',          iconFill: 'ti-home' },
    { name: 'matches',   path: '/app/matches',   label: 'Jogos',   icon: 'ti-ball-football', iconFill: 'ti-ball-football' },
    { name: 'pools',     path: '/app/pools',     label: 'Bolões',  icon: 'ti-trophy',        iconFill: 'ti-trophy' },
    { name: 'profile',   path: '/app/profile',   label: 'Perfil',  icon: 'ti-user-circle',   iconFill: 'ti-user-circle' },
];

const TAB_PATHS = tabs.map(t => t.path);

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
            // Ignore: avatar can keep fallback initials when user API is temporarily unavailable.
        }
    }
});

const showBack = computed(() => route.name === 'pool-detail' || route.name === 'pool-create');

function setTitle(t) { title.value = t; }

function isTabActive(tab) {
    if (tab.name === 'pools') return route.path.startsWith('/app/pools');
    return route.path === tab.path;
}

function currentTabIndex() {
    for (let i = 0; i < tabs.length; i++) {
        if (isTabActive(tabs[i])) return i;
    }
    return -1;
}

function navigateTo(tab) {
    const currIdx = currentTabIndex();
    const nextIdx = tabs.indexOf(tab);
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

</script>

<style scoped>
.pwa-shell {
    display: flex;
    flex-direction: column;
    height: 100dvh;
    background: #0d0f12;
    overflow: hidden;
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
    min-width: 80px;
    flex-shrink: 0;
}

.pwa-main {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
}

/* ── Tab bar ── */
.pwa-tabbar {
    display: flex;
    align-items: flex-start;
    padding-top: 8px;
    height: calc(68px + env(safe-area-inset-bottom, 0px));
    padding-bottom: env(safe-area-inset-bottom, 0px);
    background: #13161b;
    border-top: 1px solid rgba(245,166,35,0.45);
    flex-shrink: 0;
    z-index: 20;
}

.pwa-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 4px 4px 0;
    background: none;
    border: none;
    color: #4a5568;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.15s;
    -webkit-tap-highlight-color: transparent;
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
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
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
    transition: opacity 0.22s ease, transform 0.22s ease;
}
.slide-left-enter-from  { opacity: 0; transform: translateX(22px); }
.slide-left-leave-to    { opacity: 0; transform: translateX(-22px); }
.slide-right-enter-from { opacity: 0; transform: translateX(-22px); }
.slide-right-leave-to   { opacity: 0; transform: translateX(22px); }
</style>
