<template>
    <div class="pwa-login">
        <div class="pwa-login-card">
            <!-- Logo -->
            <div class="flex flex-col items-center gap-3 mb-8">
                <img :src="'/favicon.png'" class="h-16 w-16 rounded-2xl" alt="BolãoVF">
                <div class="text-center">
                    <p class="font-bc font-extrabold text-[26px] leading-none text-white">
                        Bolão<span class="text-bolao-accent">VF</span>
                    </p>
                    <p class="text-xs text-bolao-muted mt-1">Palpites e rankings ao vivo</p>
                </div>
            </div>

            <!-- Error -->
            <div v-if="error" class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                {{ error }}
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-bolao-muted mb-1.5 uppercase tracking-wider">
                        Email ou usuário
                    </label>
                    <input
                        v-model="form.login"
                        type="text"
                        autocomplete="username"
                        class="pwa-input"
                        placeholder="seu@email.com"
                        required
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-bolao-muted mb-1.5 uppercase tracking-wider">
                        Senha
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        class="pwa-input"
                        placeholder="••••••••"
                        required
                    >
                </div>
                <button type="submit" class="pwa-btn-primary w-full mt-2" :disabled="loading">
                    <span v-if="!loading">Entrar</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        Entrando...
                    </span>
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-bolao-muted2">
                Apenas diversão &middot; sem apostas financeiras
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';

const router = useRouter();
const auth = useAuthStore();

const form = ref({ login: '', password: '' });
const loading = ref(false);
const error = ref('');

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        await auth.loginUser(form.value.login, form.value.password);
        await auth.fetchMe();
        router.push('/app/dashboard');
    } catch (err) {
        const code = err.response?.data?.error?.code;
        if (code === 'AUTH_INVALID_CREDENTIALS') {
            error.value = 'Email/usuário ou senha incorretos.';
        } else if (code === 'AUTH_USER_INACTIVE') {
            error.value = 'Conta inativa. Entre em contato com o administrador.';
        } else if (code === 'AUTH_RATE_LIMITED') {
            error.value = 'Muitas tentativas. Aguarde alguns minutos.';
        } else {
            error.value = 'Erro ao entrar. Tente novamente.';
        }
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.pwa-login {
    min-height: 100dvh;
    background: #0d0f12;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px));
}

.pwa-login-card {
    width: 100%;
    max-width: 360px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.07);
    background: #13161b;
    padding: 18px 16px;
}
</style>
