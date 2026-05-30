<template>
    <div class="pwa-page">
        <div v-if="loading" class="pwa-section pt-10 flex justify-center">
            <i class="ti ti-loader-2 text-3xl text-bolao-accent animate-spin"></i>
        </div>

        <template v-else-if="matchData">
            <!-- Match Header -->
            <div class="pwa-section pt-4">
                <div class="pwa-card p-4 overflow-hidden relative">
                    <div class="absolute top-0 left-0 w-1 bg-bolao-accent h-full"></div>
                    <div class="flex justify-between items-center text-[10px] text-bolao-muted uppercase font-bold mb-3 px-1">
                        <span>{{ matchData.match.status }}</span>
                        <span>{{ formatDateTime(matchData.match.local_date) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex-1 flex flex-col items-center gap-2">
                            <img v-if="matchData.match.home_team.crest" :src="matchData.match.home_team.crest" class="w-12 h-12 object-contain" alt="">
                            <span class="text-xs font-bold text-white text-center">{{ matchData.match.home_team.name }}</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="font-bc text-4xl font-black text-white">
                                {{ matchData.match.score.home ?? 0 }} : {{ matchData.match.score.away ?? 0 }}
                            </div>
                        </div>
                        <div class="flex-1 flex flex-col items-center gap-2">
                            <img v-if="matchData.match.away_team.crest" :src="matchData.match.away_team.crest" class="w-12 h-12 object-contain" alt="">
                            <span class="text-xs font-bold text-white text-center">{{ matchData.match.away_team.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Predictions List -->
            <div class="pwa-section pt-6">
                <p class="pwa-section-label">Palpites do Grupo</p>
                <div class="space-y-2">
                    <div 
                        v-for="item in sortedPredictions" 
                        :key="item.user.id"
                        class="pwa-card p-3 flex items-center justify-between"
                        :class="{ 'border-bolao-accent/40 bg-bolao-accent/5': isMe(item.user.id) }"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-bolao-bg4 flex items-center justify-center text-[10px] font-bold text-bolao-muted">
                                {{ getInitials(item.user.name) }}
                            </div>
                            <span class="text-sm font-bold text-white">{{ item.user.name }}</span>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <template v-if="item.prediction === 'hidden'">
                                <div class="bg-bolao-bg3 px-3 py-1 rounded-lg border border-white/5">
                                    <i class="ti ti-lock text-bolao-muted2 text-xs"></i>
                                </div>
                            </template>
                            <template v-else-if="item.prediction">
                                <div class="bg-bolao-bg4 px-3 py-1 rounded-lg border border-white/10 font-bc text-lg font-bold text-white">
                                    {{ item.prediction.home_score }}×{{ item.prediction.away_score }}
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-[10px] text-bolao-muted2 italic uppercase font-bold">Sem palpite</span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../store/auth';
import client from '../api/client';

const route = useRoute();
const auth = useAuthStore();
const emit = defineEmits(['set-title']);

const loading = ref(true);
const matchData = ref(null);

const sortedPredictions = computed(() => {
    if (!matchData.value?.predictions) return [];
    return [...matchData.value.predictions].sort((a, b) => {
        if (isMe(a.user.id)) return -1;
        if (isMe(b.user.id)) return 1;
        return a.user.name.localeCompare(b.user.name);
    });
});

function isMe(userId) {
    return Number(userId) === Number(auth.user?.id);
}

function getInitials(name) {
    return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?';
}

function formatDateTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

onMounted(async () => {
    emit('set-title', 'Palpites do Jogo');
    try {
        const { data: res } = await client.get(`/pools/${route.params.poolId}/matches/${route.params.matchId}/predictions`);
        matchData.value = res.data;
    } catch (error) {
        console.error('Erro ao buscar palpites:', error);
    } finally {
        loading.value = false;
    }
});
</script>
