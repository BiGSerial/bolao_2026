import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../store/auth';

// Eager imports — CSS carrega junto com o bundle principal
import LoginView from '../views/LoginView.vue';
import AppShell from '../components/layout/AppShell.vue';
import DashboardView from '../views/DashboardView.vue';

const routes = [
    {
        path: '/pwa/login',
        name: 'login',
        component: LoginView,
        meta: { guest: true },
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
            { path: 'matches', name: 'matches', component: () => import('../views/MatchesView.vue') },
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
});

export default router;
