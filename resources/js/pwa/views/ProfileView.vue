<template>
    <div class="pwa-page pb-8">

        <!-- ── Avatar + dados ── -->
        <div class="pwa-section pt-4">
            <div class="flex items-center gap-4 pwa-card px-4 py-4">
                <div class="bolao-avatar w-14 h-14 text-base shrink-0">{{ initials }}</div>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-white text-base truncate">{{ auth.user?.name ?? '—' }}</p>
                    <p class="text-sm text-bolao-muted truncate">{{ auth.user?.email ?? '' }}</p>
                    <span v-if="auth.user?.email_verified_at === null"
                          class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full mt-1">
                        <i class="ti ti-mail-exclamation text-[10px]"></i> Email não verificado
                    </span>
                </div>
                <button @click="editingProfile = true"
                        class="shrink-0 text-bolao-accent border border-bolao-accent/30 rounded-lg px-3 py-1.5 text-xs font-bold active:opacity-60">
                    <i class="ti ti-edit text-[12px]"></i> Editar
                </button>
            </div>
        </div>

        <!-- ── Preferências ── -->
        <div class="pwa-section">
            <p class="pwa-section-label">Preferências</p>

            <!-- Push toggle -->
            <div class="pwa-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-bold text-white text-sm">Notificações Push</h3>
                        <p class="text-xs text-bolao-muted mt-0.5">Gols, resultados e avisos do bolão</p>
                        <p v-if="pushPermissionDenied" class="text-[11px] text-red-400 mt-0.5">
                            Permissão negada. Habilite nas configurações do navegador.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span v-if="push.loading.value" class="text-[10px] text-bolao-muted">
                            <i class="ti ti-loader-2 animate-spin text-xs"></i>
                        </span>
                        <span v-else-if="pushSubscribed" class="text-[10px] text-emerald-400 font-bold">Ativo</span>
                        <span v-else class="text-[10px] text-bolao-muted2 font-bold">Inativo</span>
                        <button
                            @click="togglePush"
                            :disabled="push.loading.value || pushPermissionDenied"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:opacity-40"
                            :class="pushSubscribed ? 'bg-bolao-accent' : 'bg-bolao-bg4'"
                            :aria-label="pushSubscribed ? 'Desativar notificações' : 'Ativar notificações'"
                        >
                            <span
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                :class="pushSubscribed ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                    </div>
                </div>
                <p v-if="!push.isSupported.value" class="text-[11px] text-bolao-muted2 mt-2">
                    Notificações push não são suportadas neste dispositivo/navegador.
                </p>
            </div>
        </div>

        <!-- ── Notificações ── -->
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
                <NotificationItem v-for="item in notifications" :key="item.id" :item="item" @read="markRead" />
                <button v-if="notifPage < notifTotalPages"
                        class="w-full py-2 text-xs font-semibold text-bolao-muted border border-white/[0.08] rounded-xl active:bg-bolao-bg3"
                        :disabled="loadingMoreNotifs"
                        @click="loadMoreNotifications">
                    {{ loadingMoreNotifs ? 'Carregando...' : 'Ver mais' }}
                </button>
            </div>
            <div v-else class="pwa-card px-4 py-6 text-center">
                <i class="ti ti-bell-off text-2xl text-bolao-muted2 mb-2 block"></i>
                <p class="text-sm text-bolao-muted">Sem notificações.</p>
            </div>
        </div>

        <!-- ── Conta ── -->
        <div class="pwa-section space-y-2">
            <p class="pwa-section-label">Conta</p>
            <button @click="showFeedback = true"
                    class="flex w-full items-center gap-3 pwa-card px-4 py-3 text-sm font-semibold text-slate-200 active:bg-bolao-bg3">
                <i class="ti ti-bug text-base text-bolao-muted"></i>
                Reportar problema / Sugestão
            </button>
            <button @click="doLogout"
                    class="flex w-full items-center gap-3 rounded-xl border border-red-500/20 bg-red-500/[0.05] px-4 py-3 text-sm font-semibold text-red-400 active:bg-red-500/10">
                <i class="ti ti-logout text-base"></i>
                Deslogar da conta
            </button>
            <button @click="exitApp"
                    class="flex w-full items-center gap-3 pwa-card px-4 py-3 text-sm font-semibold text-slate-200 active:bg-bolao-bg3">
                <i class="ti ti-door-exit text-base"></i>
                Sair do aplicativo
            </button>
        </div>

        <!-- ── Políticas ── -->
        <div class="pwa-section space-y-1">
            <p class="pwa-section-label">Informações</p>
            <div v-for="policy in policies" :key="policy.id" class="space-y-0">
                <button @click="togglePolicy(policy.id)"
                        class="flex w-full items-center justify-between pwa-card px-4 py-3 text-sm font-semibold text-slate-300 active:bg-bolao-bg3">
                    <span class="flex items-center gap-2">
                        <i class="ti text-bolao-muted" :class="policy.icon"></i>
                        {{ policy.title }}
                    </span>
                    <i class="ti text-bolao-muted2 text-xs transition-transform duration-200"
                       :class="activePolicyId === policy.id ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                </button>

                <!-- Content panel -->
                <div v-if="activePolicyId === policy.id"
                     class="pwa-card px-4 py-3 rounded-t-none border-t-0 text-xs text-bolao-muted leading-relaxed">

                    <!-- Loading -->
                    <div v-if="policyContent[policy.id] === 'loading'" class="flex justify-center py-4">
                        <i class="ti ti-loader-2 animate-spin text-bolao-accent"></i>
                    </div>
                    <!-- Error -->
                    <p v-else-if="policyContent[policy.id] === 'error'" class="text-red-400 text-center py-2">
                        Não foi possível carregar o documento.
                    </p>
                    <!-- Real API content (EULA / Privacy Policy) -->
                    <template v-else-if="policyContent[policy.id]">
                        <div class="flex justify-between items-center mb-3">
                            <p class="font-bold text-slate-300 text-sm">{{ policyContent[policy.id].title }}</p>
                            <span class="text-[10px] text-bolao-muted2">v{{ policyContent[policy.id].version }}</span>
                        </div>
                        <div class="legal-prose" v-html="renderMarkdown(policyContent[policy.id].content)"></div>
                        <p class="text-bolao-muted2 mt-3">
                            Publicado em {{ formatPolicyDate(policyContent[policy.id].published_at) }}
                        </p>
                    </template>
                </div>
            </div>
        </div>

        <!-- ── Copyright ── -->
        <p class="text-center text-[11px] text-bolao-muted2 px-4 pt-2 pb-4 leading-relaxed">
            © {{ new Date().getFullYear() }} BolãoVF. Todos os direitos reservados.<br>
            Versão {{ appVersion }}
        </p>

        <!-- ─────────────────────────────────────────────────────────────────── -->
        <!-- Modal: Editar perfil -->
        <!-- ─────────────────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="editingProfile" class="modal-backdrop" @click.self="cancelEdit">
                <div class="modal-box">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-white">Editar Perfil</h2>
                        <button @click="cancelEdit" class="text-bolao-muted active:opacity-60">
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-bolao-muted2 uppercase tracking-wide mb-1">Nome</label>
                            <input v-model="editForm.name" type="text" maxlength="120"
                                   class="pwa-input w-full" placeholder="Seu nome completo">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-bolao-muted2 uppercase tracking-wide mb-1">Email</label>
                            <input v-model="editForm.email" type="email"
                                   class="pwa-input w-full" placeholder="seu@email.com">
                            <p v-if="editForm.email !== auth.user?.email"
                               class="text-[11px] text-amber-400 mt-1 flex items-center gap-1">
                                <i class="ti ti-mail-exclamation text-[11px]"></i>
                                Ao salvar, um e-mail de verificação será enviado.
                            </p>
                        </div>
                    </div>

                    <p v-if="editError" class="text-[12px] text-red-400 mt-3">{{ editError }}</p>
                    <p v-if="editSuccess" class="text-[12px] text-emerald-400 mt-3 flex items-center gap-1">
                        <i class="ti ti-check text-xs"></i> {{ editSuccess }}
                    </p>

                    <div class="flex gap-2 mt-5">
                        <button @click="cancelEdit"
                                class="flex-1 py-2 text-sm font-bold text-bolao-muted border border-white/10 rounded-xl active:bg-bolao-bg3">
                            Cancelar
                        </button>
                        <button @click="saveProfile" :disabled="savingProfile"
                                class="flex-1 py-2 text-sm font-bold text-bolao-bg1 bg-bolao-accent rounded-xl active:opacity-80 disabled:opacity-40">
                            {{ savingProfile ? 'Salvando...' : 'Salvar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal: Reportar problema -->
        <Teleport to="body">
            <div v-if="showFeedback" class="modal-backdrop" @click.self="showFeedback = false">
                <div class="modal-box">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-white">Reportar Problema / Sugestão</h2>
                        <button @click="showFeedback = false" class="text-bolao-muted active:opacity-60">
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-bolao-muted2 uppercase tracking-wide mb-1">Categoria</label>
                            <div class="flex gap-2">
                                <button v-for="cat in feedbackCategories" :key="cat.value"
                                        @click="feedbackForm.category = cat.value"
                                        class="flex-1 py-2 text-xs font-bold rounded-lg border transition-colors"
                                        :class="feedbackForm.category === cat.value
                                            ? 'border-bolao-accent text-bolao-accent bg-bolao-accent/10'
                                            : 'border-white/10 text-bolao-muted bg-transparent'">
                                    {{ cat.label }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-bolao-muted2 uppercase tracking-wide mb-1">Descrição</label>
                            <textarea v-model="feedbackForm.description" rows="4" maxlength="2000"
                                      class="pwa-input w-full resize-none" placeholder="Descreva o problema ou sugestão com detalhes..."></textarea>
                            <p class="text-[10px] text-bolao-muted2 text-right mt-0.5">{{ feedbackForm.description.length }}/2000</p>
                        </div>
                    </div>

                    <p v-if="feedbackError" class="text-[12px] text-red-400 mt-2">{{ feedbackError }}</p>
                    <p v-if="feedbackSent" class="text-[12px] text-emerald-400 mt-2 flex items-center gap-1">
                        <i class="ti ti-check text-xs"></i> Enviado! Obrigado pelo feedback.
                    </p>

                    <div class="flex gap-2 mt-5">
                        <button @click="showFeedback = false"
                                class="flex-1 py-2 text-sm font-bold text-bolao-muted border border-white/10 rounded-xl active:bg-bolao-bg3">
                            Fechar
                        </button>
                        <button @click="sendFeedback" :disabled="sendingFeedback || feedbackSent"
                                class="flex-1 py-2 text-sm font-bold text-bolao-bg1 bg-bolao-accent rounded-xl active:opacity-80 disabled:opacity-40">
                            {{ sendingFeedback ? 'Enviando...' : 'Enviar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';
import { usePushNotifications } from '../composables/usePushNotifications';
import { getNotifications, markAsRead } from '../api/notifications';
import client from '../api/client';
import { getLegalDocument } from '../api/legal';
import { renderMarkdown } from '../utils/markdown';
import SkeletonCard from '../components/ui/SkeletonCard.vue';
import NotificationItem from '../components/ui/NotificationItem.vue';

const emit = defineEmits(['set-title']);
const auth    = useAuthStore();
const push    = usePushNotifications();
const router  = useRouter();

// ── Computed ──────────────────────────────────────────────────────────────────
const initials = computed(() => {
    if (!auth.user?.name) return '?';
    return auth.user.name.split(' ').filter(Boolean).map((w) => w[0].toUpperCase()).slice(0, 2).join('');
});
const unreadCount = computed(() => notifications.value.filter((n) => !n.read_at).length);

// Push: is subscribed + permission state
const pushSubscribed      = computed(() => push.isSubscribed.value);
const pushPermissionDenied = computed(() => push.permission.value === 'denied');

const appVersion = import.meta.env.VITE_PWA_BUILD_ID ?? 'dev';

// ── Notifications ─────────────────────────────────────────────────────────────
const notifications     = ref([]);
const loadingNotifications = ref(true);
const loadingMoreNotifs  = ref(false);
const notifPage          = ref(1);
const notifTotalPages    = ref(1);

async function loadNotifications(p = 1, append = false) {
    if (p === 1) loadingNotifications.value = true;
    else         loadingMoreNotifs.value = true;
    try {
        const res = await getNotifications({ page: p, per_page: 15 });
        const items = res.data.data.items ?? [];
        notifications.value = append ? [...notifications.value, ...items] : items;
        notifPage.value      = res.data.meta?.pagination?.page ?? 1;
        notifTotalPages.value = res.data.meta?.pagination?.total_pages ?? 1;
        // Atualiza badge na aba do navegador
        const unread = items.filter((n) => !n.read_at).length;
        if ('setAppBadge' in navigator && unread > 0) navigator.setAppBadge(unread).catch(() => {});
    } catch { /* silent */ } finally {
        loadingNotifications.value = false;
        loadingMoreNotifs.value    = false;
    }
}

function loadMoreNotifications() { loadNotifications(notifPage.value + 1, true); }

async function markRead(id) {
    const item = notifications.value.find((n) => n.id === id);
    if (!item || item.read_at) return;
    try {
        await markAsRead(id);
        item.read_at = new Date().toISOString();
        const unread = notifications.value.filter((n) => !n.read_at).length;
        if ('setAppBadge' in navigator) {
            unread > 0 ? navigator.setAppBadge(unread).catch(() => {}) : navigator.clearAppBadge().catch(() => {});
        }
    } catch { /* ignore */ }
}

// ── Push ─────────────────────────────────────────────────────────────────────
async function togglePush() {
    if (pushSubscribed.value) await push.unsubscribe();
    else                      await push.subscribe();
}

// ── Edit profile ──────────────────────────────────────────────────────────────
const editingProfile = ref(false);
const savingProfile  = ref(false);
const editError      = ref('');
const editSuccess    = ref('');
const editForm       = ref({ name: '', email: '' });

function openEdit() {
    editForm.value = { name: auth.user?.name ?? '', email: auth.user?.email ?? '' };
    editError.value   = '';
    editSuccess.value = '';
    editingProfile.value = true;
}

watch(editingProfile, (v) => { if (v) openEdit(); });

function cancelEdit() {
    editingProfile.value = false;
}

async function saveProfile() {
    editError.value   = '';
    editSuccess.value = '';
    if (!editForm.value.name.trim()) { editError.value = 'Nome é obrigatório.'; return; }
    savingProfile.value = true;
    try {
        const { data: res } = await client.patch('/v1/me', {
            name:  editForm.value.name.trim(),
            email: editForm.value.email.trim(),
        });
        await auth.fetchMe();
        if (res.data?.email_changed) {
            editSuccess.value = 'Salvo! Verifique seu novo e-mail para ativar.';
        } else {
            editSuccess.value = 'Perfil atualizado com sucesso.';
            setTimeout(() => { editingProfile.value = false; }, 1200);
        }
    } catch (err) {
        const msg = err?.response?.data?.message ?? Object.values(err?.response?.data?.errors ?? {})[0]?.[0];
        editError.value = msg ?? 'Erro ao salvar. Tente novamente.';
    } finally {
        savingProfile.value = false;
    }
}

// ── Feedback ──────────────────────────────────────────────────────────────────
const showFeedback    = ref(false);
const sendingFeedback = ref(false);
const feedbackSent    = ref(false);
const feedbackError   = ref('');
const feedbackForm    = ref({ category: 'bug', description: '' });

const feedbackCategories = [
    { value: 'bug',        label: '🐞 Bug'       },
    { value: 'suggestion', label: '💡 Sugestão'  },
    { value: 'other',      label: '💬 Outro'     },
];

watch(showFeedback, (v) => {
    if (v) { feedbackForm.value = { category: 'bug', description: '' }; feedbackSent.value = false; feedbackError.value = ''; }
});

async function sendFeedback() {
    feedbackError.value = '';
    if (feedbackForm.value.description.trim().length < 10) { feedbackError.value = 'Descreva com pelo menos 10 caracteres.'; return; }
    sendingFeedback.value = true;
    try {
        await client.post('/v1/feedback', feedbackForm.value);
        feedbackSent.value = true;
        setTimeout(() => { showFeedback.value = false; }, 2000);
    } catch {
        feedbackError.value = 'Erro ao enviar. Tente novamente.';
    } finally {
        sendingFeedback.value = false;
    }
}

// ── Policies ──────────────────────────────────────────────────────────────────
const activePolicyId  = ref(null);
const policyContent   = reactive({});   // { [policyId]: { title, content, version, published_at } | 'loading' | 'error' }

const policies = [
    { id: 'terms',   apiType: 'eula',          title: 'Termos de Uso',           icon: 'ti-file-text'  },
    { id: 'privacy', apiType: 'privacy-policy', title: 'Política de Privacidade', icon: 'ti-shield-lock'},
];

async function togglePolicy(id) {
    activePolicyId.value = activePolicyId.value === id ? null : id;
    if (!activePolicyId.value) return;

    const policy = policies.find((p) => p.id === id);
    if (!policy?.apiType || policyContent[id]) return; // already loaded or static

    policyContent[id] = 'loading';
    try {
        const { data: res } = await getLegalDocument(policy.apiType);
        policyContent[id] = res.data;
    } catch {
        policyContent[id] = 'error';
    }
}

function formatPolicyDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
async function doLogout() { await auth.logoutUser(); router.push('/pwa/login'); }

function exitApp() {
    auth.logoutUser();
    window.location.href = '/';
}

onMounted(() => {
    emit('set-title', 'Perfil');
    if (!auth.user) auth.fetchMe();
    loadNotifications();
});
</script>

<style scoped>
/* Form inputs */
.pwa-input {
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.12);
    background: #0f131b;
    color: #e5e7eb;
    padding: 10px 12px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.15s;
}
.pwa-input::placeholder { color: #4a5568; }
.pwa-input:focus { border-color: rgba(245,166,35,0.5); }

/* Legal document prose */
.legal-prose { font-size: 12px; line-height: 1.7; color: #94a3b8; }
.legal-prose :deep(h1), .legal-prose :deep(h2) { font-size: 13px; font-weight: 700; color: #e2e8f0; margin: 12px 0 6px; }
.legal-prose :deep(h3) { font-size: 12px; font-weight: 700; color: #e2e8f0; margin: 10px 0 4px; }
.legal-prose :deep(p)  { margin-bottom: 8px; }
.legal-prose :deep(ul), .legal-prose :deep(ol) { padding-left: 16px; margin-bottom: 8px; }
.legal-prose :deep(li) { margin-bottom: 3px; }
.legal-prose :deep(strong) { color: #e2e8f0; }

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: flex-end;
    padding: 0;
}
.modal-box {
    width: 100%;
    background: #141820;
    border-top: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px 20px 0 0;
    padding: 20px 16px 32px;
    max-height: 88dvh;
    overflow-y: auto;
}
</style>
