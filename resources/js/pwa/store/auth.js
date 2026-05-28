import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import * as authApi from '../api/auth';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(sessionStorage.getItem('pwa_token') ?? null);
    const user = ref(null);
    const legalPending = ref(false);

    const isAuthenticated = computed(() => !!token.value);

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
    }

    async function logoutUser() {
        try { await authApi.logout(); } catch {}
        token.value = null;
        user.value = null;
        sessionStorage.removeItem('pwa_token');
    }

    return { token, user, legalPending, isAuthenticated, loginUser, fetchMe, logoutUser };
});
