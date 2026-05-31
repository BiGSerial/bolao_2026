<template>
    <div class="pwa-page">
        <div class="pwa-section pt-3">
            <h1 class="text-xl font-bold text-white mb-2">Painel Admin</h1>
            <p class="text-sm text-bolao-muted">Controle global da plataforma.</p>
        </div>

        <div class="pwa-section space-y-4">
            <div class="grid grid-cols-1 gap-3">
                <div 
                    v-for="item in adminMenu" 
                    :key="item.label"
                    class="pwa-card pwa-card-hover p-4 flex items-center gap-4"
                    @click="router.push(item.path)"
                >
                    <div class="w-10 h-10 rounded-xl bg-bolao-accent/10 flex items-center justify-center text-bolao-accent">
                        <i class="ti text-xl" :class="item.icon"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-white">{{ item.label }}</h3>
                        <p class="text-xs text-bolao-muted">{{ item.description }}</p>
                    </div>
                    <i class="ti ti-chevron-right text-bolao-muted2"></i>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { onMounted } from 'vue';

const router = useRouter();
const emit = defineEmits(['set-title']);

const adminMenu = [
    { label: 'Aprovação de Usuários', icon: 'ti-users', description: 'Novos cadastros aguardando aprovação', path: '/pwa/admin/users' },
    { label: 'Controle de Grupos', icon: 'ti-tournament', description: 'Gerenciar todos os bolões criados', path: '/pwa/admin/pools' },
    { label: 'Sincronização API', icon: 'ti-refresh', description: 'Status das rodadas e times', path: '/pwa/admin/sync' },
    { label: 'API de E-mails', icon: 'ti-mail', description: 'Métricas e sincronização de e-mails transacionais', path: '/pwa/admin/emails' },
];

onMounted(() => {
    emit('set-title', 'Administração');
});
</script>
