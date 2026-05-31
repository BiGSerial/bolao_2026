import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { registerRoute, NavigationRoute } from 'workbox-routing';
import { NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies';

// ─── Precache + caching ───────────────────────────────────────────────────────
cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST);

registerRoute(
    new NavigationRoute(
        new NetworkFirst({ cacheName: 'pages-cache', networkTimeoutSeconds: 5 }),
        { denylist: [/^\/api\//] },
    ),
);

registerRoute(
    ({ request }) => request.destination === 'style' || request.destination === 'script',
    new StaleWhileRevalidate({ cacheName: 'assets-cache' }),
);

// ─── Push Notifications ───────────────────────────────────────────────────────
//
// Tags de agrupamento usadas pelo backend:
//   bolao-gol      → gols de partidas (renotify: true — vibra a cada gol)
//   bolao-pool-{id}→ eventos de bolão (pendentes, convites, ranking)
//   bolao-geral    → avisos gerais
//
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload;
    try { payload = event.data.json(); }
    catch { payload = { title: 'BolãoVF', body: event.data.text() }; }

    const title = payload.title ?? 'BolãoVF';
    const options = {
        body:      payload.body    ?? '',
        icon:      '/favicon.png',
        badge:     '/favicon.png',
        tag:       payload.tag     ?? 'bolao-geral',
        renotify:  !!payload.renotify,
        silent:    !!payload.silent,
        data:      { url: payload.url ?? '/pwa' },
    };

    // App badge (Badging API — suportado no Android + Chrome)
    const badgeCount = Number(payload.badge_count ?? 0);
    if (badgeCount > 0 && 'setAppBadge' in self.navigator) {
        self.navigator.setAppBadge(badgeCount).catch(() => {});
    }

    event.waitUntil(self.registration.showNotification(title, options));
});

// ─── Notification click ───────────────────────────────────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/pwa';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((wins) => {
            // Foca janela já aberta se possível
            for (const w of wins) {
                if ('focus' in w) {
                    if ('navigate' in w) w.navigate(url);
                    return w.focus();
                }
            }
            return clients.openWindow(url);
        }),
    );
});

// ─── Badge: limpa ao abrir o app ──────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
    if ('clearAppBadge' in self.navigator) {
        self.navigator.clearAppBadge().catch(() => {});
    }
});
