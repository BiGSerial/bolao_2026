import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/pwa.css',
                'resources/js/app.js',
                'resources/js/pwa/main.js',
            ],
            refresh: true,
        }),
        vue(),
        VitePWA({
            registerType: 'autoUpdate',
            manifest: {
                name: 'BolãoVF',
                short_name: 'BolãoVF',
                description: 'Palpites e rankings de bolões de futebol.',
                start_url: '/app',
                scope: '/',
                display: 'standalone',
                background_color: '#0b1017',
                theme_color: '#0b1017',
                icons: [
                    {
                        src: '/favicon.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/favicon.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
                additionalManifestEntries: [{ url: '/offline', revision: null }],
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) => request.mode === 'navigate',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages-cache',
                            networkTimeoutSeconds: 5,
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        urlPattern: ({ request }) => request.destination === 'style' || request.destination === 'script',
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'assets-cache',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                ],
                navigateFallback: '/offline',
            },
        }),
    ],
});
