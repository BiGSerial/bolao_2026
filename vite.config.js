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
            strategies: 'injectManifest',
            srcDir: 'resources/js/pwa',
            filename: 'sw.js',
            registerType: 'autoUpdate',
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
                additionalManifestEntries: [{ url: '/offline', revision: null }],
            },
            manifest: {
                name: 'BolãoVF',
                short_name: 'BolãoVF',
                description: 'Palpites e rankings de bolões de futebol.',
                start_url: '/pwa/',
                scope: '/',
                display: 'standalone',
                background_color: '#0b1017',
                theme_color: '#0b1017',
                icons: [
                    { src: '/favicon.png', sizes: '192x192', type: 'image/png' },
                    { src: '/favicon.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
                ],
            },
        }),
    ],
});
