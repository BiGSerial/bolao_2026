import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';

// Eager imports
import LoginView from '../views/LoginView.vue';
import AppShell from '../components/layout/AppShell.vue';
import DashboardView from '../views/DashboardView.vue';
import LegalAcceptanceView from '../views/LegalAcceptanceView.vue';

const routes = [
    {
        path: '/pwa/login',
        name: 'login',
        component: LoginView,
        meta: { guest: true },
    },
    {
        path: '/pwa/legal',
        name: 'legal-acceptance',
        component: LegalAcceptanceView,
        meta: { requiresAuth: true },
    },
    {
        path: '/pwa',
        component: AppShell,
        meta: { requiresAuth: true },
        children: [
            { path: '', redirect: '/pwa/dashboard' },
            { path: 'dashboard', name: 'dashboard', component: DashboardView },
            { path: 'pools', name: 'pools', component: () => import('../views/PoolsView.vue') },
            { path: 'pools/create', name: 'pool-create', component: () => import('../views/PoolCreateView.vue') },
            { path: 'pools/:id', name: 'pool-detail', component: () => import('../views/PoolDetailView.vue') },
            { path: 'pools/:poolId/matches/:matchId', name: 'pool-match-detail', component: () => import('../views/PoolMatchDetailView.vue') },
            { path: 'matches', name: 'matches', component: () => import('../views/MatchesView.vue') },
            { path: 'matches/:matchId', name: 'match-detail', component: () => import('../views/PoolMatchDetailView.vue') },
            { path: 'management', name: 'management', component: () => import('../views/ManagementView.vue') },
            { path: 'management/pools/:id', name: 'management-pool', component: () => import('../views/ManagementPoolView.vue') },
            { path: 'admin', name: 'admin', component: () => import('../views/AdminView.vue') },
            { path: 'admin/users', name: 'admin-users', component: () => import('../views/admin/AdminUsersView.vue') },
            { path: 'admin/pools', name: 'admin-pools', component: () => import('../views/admin/AdminPoolsView.vue') },
            { path: 'admin/pools/:id', name: 'admin-pool-detail', component: () => import('../views/admin/AdminPoolDetailView.vue') },
            { path: 'admin/sync', name: 'admin-sync', component: () => import('../views/admin/AdminSyncView.vue') },
            { path: 'admin/emails', name: 'admin-emails', component: () => import('../views/admin/AdminEmailsView.vue') },
            { path: 'profile', name: 'profile', component: () => import('../views/ProfileView.vue') },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/pwa' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) return '/pwa/login';
    if (to.meta.guest && auth.isAuthenticated) return '/pwa/dashboard';

    // Gate de aceite legal — bloqueia acesso ao app enquanto houver docs pendentes
    if (to.meta.requiresAuth && auth.isAuthenticated && auth.legalPending) {
        if (to.name !== 'legal-acceptance') return '/pwa/legal';
    }
});

export default router;
