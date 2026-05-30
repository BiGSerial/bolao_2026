<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3">
            <h1 class="text-xl font-bold text-white mb-2">Controle de Grupos</h1>
            <p class="text-sm text-bolao-muted mb-4">Monitoramento global de bolões.</p>

            <!-- Search -->
            <div class="relative mb-6">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-bolao-muted"></i>
                <input 
                    v-model="search" 
                    type="text" 
                    placeholder="Buscar bolão por nome..." 
                    class="pwa-input pl-12"
                    @input="debounceSearch"
                >
            </div>
        </div>

        <div v-if="loading && !pools.length" class="pwa-section pt-10 flex justify-center">
            <i class="ti ti-loader-2 text-3xl text-bolao-accent animate-spin"></i>
        </div>

        <div v-else-if="pools.length" class="pwa-section space-y-3">
            <div v-for="pool in pools" :key="pool.id" class="pwa-card p-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-bold text-white truncate">{{ pool.name }}</h3>
                            <span :class="statusBadgeCls(pool.status)" class="text-[9px] px-1.5 py-0.5 rounded font-black uppercase">
                                {{ pool.status }}
                            </span>
                        </div>
                        <p class="text-[10px] text-bolao-accent uppercase tracking-widest">{{ pool.competition }}</p>
                        <p class="text-xs text-bolao-muted truncate">Dono: {{ pool.owner }} · {{ pool.members_count }} membros</p>
                    </div>
                    <button class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-bolao-accent active:bg-white/10" @click="openPoolDetails(pool)">
                        <i class="ti ti-eye text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.current_page < pagination.last_page" class="pt-4">
                <button class="pwa-btn-secondary w-full" :disabled="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Carregando...' : 'Ver mais bolões' }}
                </button>
            </div>
        </div>

        <!-- Pool Details Modal -->
        <Transition name="fade">
            <div v-if="selectedPool" class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-4" @click.self="selectedPool = null">
                <Transition name="slide-up">
                    <div class="w-full max-w-md bg-bolao-bg2 rounded-t-3xl p-6 pb-8 shadow-2xl border-t border-white/5 flex flex-col max-h-[90vh]">
                        <div class="flex flex-col items-center mb-6 shrink-0">
                            <div class="w-12 h-1px bg-white/10 rounded-full mb-4"></div>
                            <h2 class="text-lg font-bold text-white text-center">{{ selectedPool.name }}</h2>
                            <p class="text-xs text-bolao-muted uppercase tracking-widest">{{ selectedPool.competition }}</p>
                        </div>

                        <!-- Actions Bar -->
                        <div class="flex gap-2 mb-6 shrink-0">
                            <button 
                                v-if="selectedPool.status === 'active'"
                                class="flex-1 flex flex-col items-center gap-1 p-3 rounded-2xl bg-amber-500/10 text-amber-500 font-bold active:scale-95"
                                @click="updateStatus('suspended')"
                            >
                                <i class="ti ti-player-pause text-xl"></i>
                                <span class="text-[10px] uppercase">Suspender</span>
                            </button>
                            <button 
                                v-else
                                class="flex-1 flex flex-col items-center gap-1 p-3 rounded-2xl bg-bolao-green/10 text-bolao-green font-bold active:scale-95"
                                @click="updateStatus('active')"
                            >
                                <i class="ti ti-player-play text-xl"></i>
                                <span class="text-[10px] uppercase">Ativar</span>
                            </button>
                            
                            <button class="flex-1 flex flex-col items-center gap-1 p-3 rounded-2xl bg-white/5 text-slate-300 font-bold active:scale-95" @click="goToPool">
                                <i class="ti ti-external-link text-xl"></i>
                                <span class="text-[10px] uppercase">Ver Bolão</span>
                            </button>
                        </div>

                        <!-- Members List -->
                        <p class="text-[10px] font-bold text-bolao-muted uppercase tracking-widest mb-3 shrink-0">Membros do Grupo ({{ selectedPool.members?.length || 0 }})</p>
                        <div class="flex-1 overflow-y-auto space-y-2 mb-4 no-scrollbar">
                            <div v-for="m in selectedPool.members" :key="m.id" class="flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-white truncate">{{ m.name }}</p>
                                    <p class="text-[10px] text-bolao-muted truncate">{{ m.role }} · {{ m.status }}</p>
                                </div>
                                <span v-if="m.role === 'owner'" class="text-[9px] bg-bolao-accent/20 text-bolao-accent px-1.5 py-0.5 rounded font-black uppercase">Dono</span>
                            </div>
                        </div>

                        <button class="w-full p-4 rounded-2xl bg-white/5 text-slate-300 font-bold active:bg-white/10 shrink-0" @click="selectedPool = null">
                            Fechar
                        </button>
                    </div>
                </Transition>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import client from '../../api/client';
import Swal from 'sweetalert2';

const router = useRouter();
const emit = defineEmits(['set-title']);
const loading = ref(true);
const loadingMore = ref(false);
const pools = ref([]);
const search = ref('');
const selectedPool = ref(null);
const pagination = ref({ current_page: 1, last_page: 1 });

let searchTimeout = null;

async function fetchPools(append = false) {
    if (!append) loading.value = true;
    else loadingMore.value = true;

    try {
        const { data: res } = await client.get('/admin/pools', {
            params: {
                search: search.value,
                page: append ? pagination.value.current_page + 1 : 1
            }
        });
        
        pools.value = append ? [...pools.value, ...res.data.items] : res.data.items;
        pagination.value = res.data.pagination;
    } catch { /* ignore */ } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchPools(), 500);
}

function loadMore() {
    fetchPools(true);
}

async function openPoolDetails(pool) {
    loading.value = true;
    try {
        const { data: res } = await client.get(`/admin/pools/${pool.id}`);
        selectedPool.value = res.data;
    } catch {
        Swal.fire('Erro', 'Não foi possível carregar os detalhes do grupo.', 'error');
    } finally {
        loading.value = false;
    }
}

async function updateStatus(newStatus) {
    const pool = selectedPool.value;
    try {
        await client.patch(`/admin/pools/${pool.id}/status`, { status: newStatus });
        pool.status = newStatus;
        // Update in list too
        const inList = pools.value.find(p => p.id === pool.id);
        if (inList) inList.status = newStatus;

        Swal.fire({
            icon: 'success', title: 'Sucesso', text: `Grupo ${pool.name} agora está ${newStatus}.`,
            toast: true, position: 'top', timer: 3000, showConfirmButton: false
        });
    } catch {
        Swal.fire('Erro', 'Falha ao atualizar status do grupo.', 'error');
    }
}

function goToPool() {
    const id = selectedPool.value.id;
    selectedPool.value = null;
    router.push(`/pwa/pools/${id}`);
}

function statusBadgeCls(s) {
    if (s === 'active') return 'bg-bolao-green/20 text-bolao-green';
    if (s === 'suspended') return 'bg-amber-500/20 text-amber-500';
    return 'bg-white/10 text-bolao-muted';
}

onMounted(() => {
    emit('set-title', 'Grupos');
    fetchPools();
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1); }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }

.h-1px { height: 4px; width: 40px; }
</style>
