<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3">
            <h1 class="text-xl font-bold text-white mb-2">Gestão de Bolões</h1>
            <p class="text-sm text-bolao-muted">Gerencie os grupos que você coordena.</p>
        </div>

        <div v-if="loading" class="pwa-section space-y-3">
            <div v-for="i in 3" :key="i" class="pwa-skeleton h-20 w-full"></div>
        </div>

        <div v-else-if="managedPools.length" class="pwa-section space-y-3">
            <div 
                v-for="pool in managedPools" 
                :key="pool.id" 
                class="pwa-card pwa-card-hover p-4 flex items-center justify-between"
                @click="goToPoolManagement(pool)"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold text-bolao-accent uppercase tracking-widest mb-1">
                        {{ pool.competition?.name }}
                    </p>
                    <h3 class="text-lg font-bold text-white truncate">{{ pool.name }}</h3>
                    <p class="text-xs text-bolao-muted">
                        {{ pool.members_count }} membros · {{ pool.pending_count }} pendentes
                    </p>
                </div>
                <i class="ti ti-chevron-right text-bolao-muted"></i>
            </div>
        </div>

        <div v-else class="pwa-section py-12 text-center">
            <i class="ti ti-layout-dashboard text-4xl text-bolao-muted2 mb-3 block"></i>
            <p class="text-bolao-muted">Você não gerencia nenhum bolão ativo.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import client from '../api/client';

const router = useRouter();
const loading = ref(true);
const managedPools = ref([]);

const emit = defineEmits(['set-title']);

onMounted(async () => {
    emit('set-title', 'Gestão');
    try {
        const { data: res } = await client.get('/pools?managed=1');
        managedPools.value = res.data.items || [];
    } catch (error) {
        console.error('Erro ao buscar bolões gerenciados:', error);
    } finally {
        loading.value = false;
    }
});

function goToPoolManagement(pool) {
    router.push(`/pwa/management/pools/${pool.id}`);
}
</script>
