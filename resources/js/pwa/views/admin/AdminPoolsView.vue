<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3">
            <h1 class="text-xl font-bold text-white mb-2">Controle de Grupos</h1>
            <p class="text-sm text-bolao-muted mb-4">Monitoramento global de bolões.</p>

            <div class="relative mb-4">
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

        <div v-else-if="pools.length" class="pwa-section space-y-3 pb-6">
            <article v-for="pool in pools" :key="pool.id" class="pwa-card p-4 flex items-center justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-bold text-white truncate">{{ pool.name }}</h3>
                        <span :class="statusBadgeCls(pool.status)" class="text-[9px] px-1.5 py-0.5 rounded font-black uppercase">{{ pool.status }}</span>
                    </div>
                    <p class="text-[10px] text-bolao-accent uppercase tracking-widest">{{ pool.competition }}</p>
                    <p class="text-xs text-bolao-muted truncate">Dono: {{ pool.owner }} · {{ pool.members_count }} membros</p>
                </div>
                <button class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-bolao-accent active:bg-white/10" @click="openPool(pool.id)">
                    <i class="ti ti-eye text-xl"></i>
                </button>
            </article>

            <div v-if="pagination.current_page < pagination.last_page" class="pt-2">
                <button class="pwa-btn-secondary w-full" :disabled="loadingMore" @click="loadMore">
                    {{ loadingMore ? 'Carregando...' : 'Ver mais grupos' }}
                </button>
            </div>
        </div>

        <div v-else class="pwa-section py-12 text-center text-bolao-muted">Nenhum grupo encontrado.</div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import client from '../../api/client';

const router = useRouter();
const emit = defineEmits(['set-title']);
const loading = ref(true);
const loadingMore = ref(false);
const pools = ref([]);
const search = ref('');
const pagination = ref({ current_page: 1, last_page: 1 });
let searchTimeout = null;

async function fetchPools(append = false) {
    if (!append) loading.value = true;
    else loadingMore.value = true;

    try {
        const { data: res } = await client.get('/admin/pools', {
            params: {
                search: search.value,
                page: append ? pagination.value.current_page + 1 : 1,
            }
        });

        pools.value = append ? [...pools.value, ...res.data.items] : res.data.items;
        pagination.value = res.data.pagination;
    } finally {
        loading.value = false;
        loadingMore.value = false;
    }
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchPools(), 400);
}

function loadMore() {
    fetchPools(true);
}

function openPool(id) {
    router.push(`/pwa/admin/pools/${id}`);
}

function statusBadgeCls(s) {
    if (s === 'active') return 'bg-bolao-green/20 text-bolao-green';
    if (s === 'suspended') return 'bg-amber-500/20 text-amber-500';
    if (s === 'blocked') return 'bg-red-500/20 text-red-400';
    return 'bg-white/10 text-bolao-muted';
}

onMounted(() => {
    emit('set-title', 'Grupos');
    fetchPools();
});
</script>
