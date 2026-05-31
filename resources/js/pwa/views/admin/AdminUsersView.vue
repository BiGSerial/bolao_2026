<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3">
            <h1 class="text-xl font-bold text-white mb-2">Gerenciar Usuários</h1>
            <p class="text-sm text-bolao-muted mb-4">Moderação e controle de acessos.</p>

            <!-- Search & Filter -->
            <div class="space-y-3 mb-6">
                <div class="relative">
                    <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-bolao-muted"></i>
                    <input 
                        v-model="search" 
                        type="text" 
                        placeholder="Buscar por nome ou email..." 
                        class="pwa-input pl-12"
                        @input="debounceSearch"
                    >
                </div>
                <div class="flex gap-2 overflow-x-auto no-scrollbar pb-1">
                    <button 
                        v-for="s in statusOptions" 
                        :key="s.value"
                        class="comp-chip"
                        :class="status === s.value ? 'active' : 'inactive'"
                        @click="status = s.value"
                    >
                        {{ s.label }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="loading && !users.length" class="pwa-section pt-10 flex justify-center">
            <i class="ti ti-loader-2 text-3xl text-bolao-accent animate-spin"></i>
        </div>

        <div v-else-if="users.length" class="pwa-section space-y-3">
            <div v-for="user in users" :key="user.id" class="pwa-card p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-bold text-white truncate">{{ user.name }}</h3>
                            <span :class="statusBadgeCls(user.status)" class="text-[9px] px-1.5 py-0.5 rounded font-black uppercase">
                                {{ user.status }}
                            </span>
                        </div>
                        <p class="text-xs text-bolao-muted truncate">{{ user.email }}</p>
                    </div>
                    <button class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-bolao-accent active:bg-white/10" @click="openUserActions(user)">
                        <i class="ti ti-dots-vertical text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.current_page < pagination.last_page" class="pt-4">
                <button class="pwa-btn-secondary w-full" :disabled="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Carregando...' : 'Ver mais usuários' }}
                </button>
            </div>
        </div>

        <div v-else class="pwa-section py-12 text-center text-bolao-muted">
            Nenhum usuário encontrado.
        </div>

        <!-- User Actions Modal (Bottom Sheet feel) -->
        <Transition name="fade">
            <div v-if="selectedUser" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4" @click.self="selectedUser = null">
                <Transition name="slide-up">
                    <div class="w-full max-w-md bg-bolao-bg2 rounded-t-3xl p-6 pb-8 space-y-4 shadow-2xl border-t border-white/5">
                        <div class="flex flex-col items-center mb-2">
                            <div class="w-12 h-1px bg-white/10 rounded-full mb-4"></div>
                            <h2 class="text-lg font-bold text-white">{{ selectedUser.name }}</h2>
                            <p class="text-sm text-bolao-muted">{{ selectedUser.email }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-2 pt-2">
                            <button 
                                v-if="selectedUser.status === 'pending'"
                                class="flex items-center gap-3 w-full p-4 rounded-2xl bg-bolao-green/10 text-bolao-green font-bold active:scale-95 transition-transform"
                                @click="updateStatus('active')"
                            >
                                <i class="ti ti-check text-xl"></i> Aprovar Usuário
                            </button>
                            
                            <button 
                                v-if="selectedUser.status === 'active'"
                                class="flex items-center gap-3 w-full p-4 rounded-2xl bg-amber-500/10 text-amber-500 font-bold active:scale-95 transition-transform"
                                @click="updateStatus('suspended')"
                            >
                                <i class="ti ti-player-pause text-xl"></i> Suspender (Banir)
                            </button>

                            <button 
                                v-if="['suspended', 'rejected'].includes(selectedUser.status)"
                                class="flex items-center gap-3 w-full p-4 rounded-2xl bg-bolao-accent/10 text-bolao-accent font-bold active:scale-95 transition-transform"
                                @click="updateStatus('active')"
                            >
                                <i class="ti ti-player-play text-xl"></i> Reativar Usuário
                            </button>

                            <button 
                                class="flex items-center gap-3 w-full p-4 rounded-2xl bg-blue-500/10 text-blue-400 font-bold active:scale-95 transition-transform"
                                @click="resendPassword"
                            >
                                <i class="ti ti-key text-xl"></i> Gerar Senha Temporária
                            </button>

                            <button 
                                class="flex items-center gap-3 w-full p-4 rounded-2xl bg-red-500/10 text-red-500 font-bold active:scale-95 transition-transform"
                                @click="confirmDelete"
                            >
                                <i class="ti ti-trash text-xl"></i> Remover Permanentemente
                            </button>

                            <button 
                                class="w-full p-4 mt-2 rounded-2xl bg-white/5 text-slate-300 font-bold active:bg-white/10"
                                @click="selectedUser = null"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import client from '../../api/client';
import Swal from 'sweetalert2';

const emit = defineEmits(['set-title']);
const loading = ref(true);
const loadingMore = ref(false);
const users = ref([]);
const search = ref('');
const status = ref('');
const selectedUser = ref(null);
const pagination = ref({ current_page: 1, last_page: 1 });

const statusOptions = [
    { label: 'Todos', value: '' },
    { label: 'Pendentes', value: 'pending' },
    { label: 'Ativos', value: 'active' },
    { label: 'Suspensos', value: 'suspended' },
];

let searchTimeout = null;

async function fetchUsers(append = false) {
    if (!append) loading.value = true;
    else loadingMore.value = true;

    try {
        const { data: res } = await client.get('/admin/users', {
            params: {
                search: search.value,
                status: status.value,
                page: append ? pagination.value.current_page + 1 : 1
            }
        });
        
        users.value = append ? [...users.value, ...res.data.items] : res.data.items;
        pagination.value = res.data.pagination;
    } catch { /* ignore */ } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchUsers(), 500);
}

function loadMore() {
    fetchUsers(true);
}

function openUserActions(user) {
    selectedUser.value = user;
}

async function updateStatus(newStatus) {
    const user = selectedUser.value;
    try {
        await client.patch(`/admin/users/${user.id}/status`, { status: newStatus });
        user.status = newStatus;
        selectedUser.value = null;
        Swal.fire({
            icon: 'success',
            title: 'Sucesso',
            text: `Usuário ${user.name} agora está ${newStatus}.`,
            toast: true, position: 'top', timer: 3000, showConfirmButton: false
        });
    } catch (err) {
        Swal.fire('Erro', err.response?.data?.message || 'Falha ao atualizar status.', 'error');
    }
}

async function resendPassword() {
    const user = selectedUser.value;
    try {
        const { data: res } = await client.post(`/admin/users/${user.id}/reset-password`);
        selectedUser.value = null;
        Swal.fire({
            title: 'Senha Gerada',
            html: `A nova senha temporária para <b>${user.name}</b> é:<br><br><span class="text-2xl font-mono text-bolao-accent">${res.data.temp_password}</span><br><br>O usuário precisará trocá-la no próximo acesso.`,
            icon: 'info',
            confirmButtonText: 'Copiar e Fechar'
        }).then(() => {
            navigator.clipboard.writeText(res.data.temp_password);
        });
    } catch {
        Swal.fire('Erro', 'Falha ao gerar senha.', 'error');
    }
}

async function confirmDelete() {
    const user = selectedUser.value;
    const result = await Swal.fire({
        title: 'Tem certeza?',
        text: `Remover ${user.name} permanentemente? Todos os seus palpites e histórico serão apagados.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Sim, remover!',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            await client.delete(`/admin/users/${user.id}`);
            users.value = users.value.filter(u => u.id !== user.id);
            selectedUser.value = null;
            Swal.fire('Removido', 'Usuário excluído com sucesso.', 'success');
        } catch {
            Swal.fire('Erro', 'Falha ao excluir usuário.', 'error');
        }
    }
}

function statusBadgeCls(s) {
    if (s === 'pending') return 'bg-amber-500/20 text-amber-500';
    if (s === 'active') return 'bg-bolao-green/20 text-bolao-green';
    if (s === 'suspended') return 'bg-red-500/20 text-red-500';
    return 'bg-white/10 text-bolao-muted';
}

watch(status, () => fetchUsers());

onMounted(() => {
    emit('set-title', 'Usuários');
    fetchUsers();
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
