import { defineStore } from 'pinia';
import { ref, watch } from 'vue';
import * as standingsApi from '../api/standings';

export const useAppStore = defineStore('app', () => {
    const competitions = ref([]);
    const selectedCompetitionId = ref(
        localStorage.getItem('pwa_competition_id') 
            ? parseInt(localStorage.getItem('pwa_competition_id'), 10) 
            : null
    );
    const loadingCompetitions = ref(false);

    async function fetchCompetitions() {
        if (competitions.value.length > 0) return;
        loadingCompetitions.ref = true;
        try {
            const { data: res } = await standingsApi.getStandings();
            competitions.value = res.data.competitions || [];
            
            // If no competition selected, pick the first one from API
            if (!selectedCompetitionId.value && competitions.value.length > 0) {
                selectedCompetitionId.value = competitions.value[0].id;
            }
        } catch (error) {
            console.error('Erro ao buscar competições:', error);
        } finally {
            loadingCompetitions.value = false;
        }
    }

    function setCompetition(id) {
        selectedCompetitionId.value = id;
        localStorage.setItem('pwa_competition_id', id);
    }

    return { 
        competitions, 
        selectedCompetitionId, 
        loadingCompetitions, 
        fetchCompetitions, 
        setCompetition 
    };
});
