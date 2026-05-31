import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import { existsSync, copyFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

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
            // Não usa virtual:pwa-register — registro feito manualmente em main.js
            // para controlar o escopo e o arquivo /sw.js na raiz.
            registerType: 'prompt',
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,png,svg,ico,woff2}'],
                additionalManifestEntries: [{ url: '/offline', revision: null }],
            },
            manifest: {
                name: 'BolãoVF',
                short_name: 'BolãoVF',
                description: 'Palpites e rankings de bolões de futebol.',
                start_url: '/pwa/',
                scope: '/pwa/',
                display: 'standalone',
                background_color: '#0b1017',
                theme_color: '#0b1017',
                icons: [
                    { src: '/favicon.png', sizes: '192x192', type: 'image/png' },
                    { src: '/favicon.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
                ],
            },
        }),
        // Copia public/build/sw.js → public/sw.js após o build para que o SW
        // seja servido pela raiz do domínio e possa ter escopo /pwa/ sem
        // precisar do header Service-Worker-Allowed.
        {
            name: 'copy-sw-to-public-root',
            apply: 'build',
            closeBundle() {
                const root = dirname(fileURLToPath(import.meta.url));
                const src  = resolve(root, 'public/build/sw.js');
                const dest = resolve(root, 'public/sw.js');
                if (existsSync(src)) {
                    copyFileSync(src, dest);
                    console.log('[vite] sw.js → public/sw.js');
                }
            },
        },
    ],
});
