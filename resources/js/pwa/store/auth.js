import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import * as authApi from '../api/auth';

export const useAuthStore = defineStore('auth', () => {
    const token        = ref(sessionStorage.getItem('pwa_token') ?? null);
    const user         = ref(null);
    const legalPending = ref(false);

    const isAuthenticated = computed(() => !!token.value);
    const isAdmin         = computed(() => !!user.value?.is_admin);
    const isManager       = computed(() => !!user.value?.is_manager);

    async function loginUser(loginField, password) {
        const { data: res } = await authApi.login(loginField, password);
        token.value = res.data.token;
        sessionStorage.setItem('pwa_token', res.data.token);
        legalPending.value = res.data.flags?.legal_pending ?? false;
        return res.data;
    }

    async function fetchMe() {
        const { data: res } = await authApi.me();
        user.value = res.data;
        // Sincroniza legalPending do servidor a cada fetchMe
        // (ao abrir o app com token existente ou após troca de aba)
        if (typeof res.data?.legal_pending === 'boolean') {
            legalPending.value = res.data.legal_pending;
        }
    }

    function clearLegalPending() {
        legalPending.value = false;
    }

    async function logoutUser() {
        try { await authApi.logout(); } catch {}
        token.value    = null;
        user.value     = null;
        legalPending.value = false;
        sessionStorage.removeItem('pwa_token');
    }

    return {
        token,
        user,
        legalPending,
        isAuthenticated,
        isAdmin,
        isManager,
        loginUser,
        fetchMe,
        fetchMe,
        clearLegalPending,
        logoutUser,
    };
});
