<template>
    <div class="pwa-page">

        <!-- User card -->
        <div class="pwa-section pt-4">
            <div class="flex items-center gap-4 rounded-xl border border-white/[0.07] bg-bolao-bg2 px-4 py-4">
                <div class="bolao-avatar w-14 h-14 text-base shrink-0">{{ initials }}</div>
                <div class="min-w-0">
                    <p class="font-bold text-white text-base truncate">{{ auth.user?.name ?? '—' }}</p>
                    <p class="text-sm text-bolao-muted truncate">{{ auth.user?.email ?? '' }}</p>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="pwa-section">
            <div class="flex items-center justify-between mb-3">
                <p class="pwa-section-label mb-0">
                    Notificações
                    <span v-if="unreadCount > 0"
                          class="ml-2 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-bolao-accent px-1 text-[9px] font-bold text-black">
                        {{ unreadCount }}
                    </span>
                </p>
                <button v-if="notifications.length" class="text-[11px] text-bolao-accent font-semibold" @click="loadNotifications">
                    <i class="ti ti-refresh text-xs"></i> Atualizar
                </button>
            </div>

            <div v-if="loadingNotifications" class="space-y-2">
                <SkeletonCard v-for="i in 3" :key="i" />
            </div>

            <div v-else-if="notifications.length" class="space-y-2">
                <NotificationItem
                    v-for="item in notifications"
                    :key="item.id"
                    :item="item"
                    @read="markRead"
                />
                <button
                    v-if="notifPage < notifTotalPages"
                    class="w-full py-2 text-xs font-semibold text-bolao-muted border border-white/[0.08] rounded-xl active:bg-bolao-bg3 transition-colors"
                    :disabled="loadingMoreNotifs"
                    @click="loadMoreNotifications"
                >
                    {{ loadingMoreNotifs ? 'Carregando...' : 'Ver mais' }}
                </button>
            </div>

            <div v-else class="rounded-xl border border-white/[0.07] bg-bolao-bg2 px-4 py-6 text-center">
                <i class="ti ti-bell-off text-2xl text-bolao-muted2 mb-2 block"></i>
                <p class="text-sm text-bolao-muted">Sem notificações.</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="pwa-section space-y-2">
            <p class="pwa-section-label">Conta</p>
            <button
                class="flex w-full items-center gap-3 rounded-xl border border-red-500/20 bg-red-500/[0.05] px-4 py-3 text-sm font-semibold text-red-400 active:bg-red-500/10 transition-colors"
                @click="doLogout"
            >
                <i class="ti ti-logout text-base"></i>
                Deslogar da conta
            </button>
            <button
                class="flex w-full items-center gap-3 rounded-xl border border-white/[0.10] bg-bolao-bg2 px-4 py-3 text-sm font-semibold text-slate-200 active:bg-bolao-bg3 transition-colors"
                @click="exitApp"
            >
                <i class="ti ti-door-exit text-base"></i>
                Sair do aplicativo
            </button>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { getNotifications, markAsRead } from '../api/notifications';
import SkeletonCard from '../components/ui/SkeletonCard.vue';
import NotificationItem from '../components/ui/NotificationItem.vue';

const emit = defineEmits(['set-title']);
const auth = useAuthStore();
const router = useRouter();

const notifications = ref([]);
const loadingNotifications = ref(true);
const loadingMoreNotifs = ref(false);
const notifPage = ref(1);
const notifTotalPages = ref(1);

const initials = computed(() => {
    if (!auth.user?.name) return '?';
    return auth.user.name.split(' ').filter(Boolean).map((w) => w[0].toUpperCase()).slice(0, 2).join('');
});

const unreadCount = computed(() => notifications.value.filter((n) => !n.read_at).length);

async function loadNotifications(p = 1, append = false) {
    if (p === 1) loadingNotifications.value = true;
    else loadingMoreNotifs.value = true;
    try {
        const res = await getNotifications({ page: p, per_page: 15 });
        const items = res.data.data.items ?? [];
        notifications.value = append ? [...notifications.value, ...items] : items;
        notifPage.value = res.data.meta?.pagination?.page ?? 1;
        notifTotalPages.value = res.data.meta?.pagination?.total_pages ?? 1;
    } catch {
        // silently fail
    } finally {
        loadingNotifications.value = false;
        loadingMoreNotifs.value = false;
    }
}

function loadMoreNotifications() {
    loadNotifications(notifPage.value + 1, true);
}

async function markRead(id) {
    const item = notifications.value.find((n) => n.id === id);
    if (!item || item.read_at) return;
    try {
        await markAsRead(id);
        item.read_at = new Date().toISOString();
    } catch { /* ignore */ }
}

async function doLogout() {
    await auth.logoutUser();
    router.push('/pwa/login');
}

async function exitApp() {
    await auth.logoutUser();
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) {
        window.location.replace('/');
        return;
    }
    window.location.href = '/';
}

onMounted(() => {
    emit('set-title', 'Perfil');
    if (!auth.user) auth.fetchMe();
    loadNotifications();
});
</script>
