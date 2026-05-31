import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { registerSW } from 'virtual:pwa-register';
import App from './App.vue';
import router from './router';

import '@fontsource/barlow/400.css';
import '@fontsource/barlow/600.css';
import '@fontsource/barlow/700.css';
import '@fontsource/barlow-condensed/700.css';
import '@fontsource/barlow-condensed/800.css';
import '@tabler/icons-webfont/dist/tabler-icons.css';
import './pwa.css';

let appUpdateInProgress = false;
const BUILD_ID_KEY = 'pwa_build_id';

function showUpdatingBanner() {
    let banner = document.getElementById('pwa-updating-banner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'pwa-updating-banner';
        Object.assign(banner.style, {
            position: 'fixed',
            left: '50%',
            top: '12px',
            transform: 'translateX(-50%)',
            padding: '7px 14px',
            borderRadius: '999px',
            fontSize: '12px',
            fontWeight: '700',
            zIndex: '10000',
            background: '#f5a623',
            color: '#111827',
            boxShadow: '0 8px 20px rgba(0,0,0,.35)',
        });
        document.body.appendChild(banner);
    }
    banner.textContent = 'Atualizando aplicativo...';
}

async function clearAllCaches() {
    if (!('caches' in window)) return;
    const keys = await caches.keys();
    await Promise.all(keys.map((k) => caches.delete(k)));
}

const updateSW = registerSW({
    immediate: true,
    onNeedRefresh() {
        forceAppUpdate();
    },
    onRegisteredSW(_, registration) {
        if (!registration) return;
        setInterval(() => {
            registration.update().catch(() => {});
        }, 60_000);
    },
});

async function forceAppUpdate() {
    if (appUpdateInProgress) return;
    appUpdateInProgress = true;
    showUpdatingBanner();

    // Deixa o SW gerenciar o próprio cache (ele apaga versões antigas no activate).
    // NÃO limpamos manualmente — apagar o precache do novo SW força tudo a vir da
    // rede e qualquer falha de chunk derruba o app inteiro (incluindo o tabbar).
    //
    // updateSW(true) posta SKIP_WAITING e faz reload via controllerchange.
    // NÃO chamamos window.location.reload() depois — causaria duplo reload
    // e interromperia o Vue no meio do mount.
    try {
        await updateSW(true);
    } catch {
        // updateSW falhou (ex: sem SW esperando); recarrega como fallback.
        window.location.reload();
    }
}

async function checkBuildSignature() {
    try {
        const res = await fetch('/api/v1/app-config', {
            method: 'GET',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!res.ok) return;
        const json = await res.json();
        const incoming = String(json?.data?.build_id ?? '').trim();
        if (!incoming) return;

        const previous = String(localStorage.getItem(BUILD_ID_KEY) ?? '').trim();
        if (!previous) {
            localStorage.setItem(BUILD_ID_KEY, incoming);
            return;
        }

        if (previous !== incoming) {
            localStorage.setItem(BUILD_ID_KEY, incoming);
            await forceAppUpdate();
        }
    } catch {
        // offline/network error: ignore
    }
}

// A2HS banner
(function () {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    if (path !== '/pwa') return;
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) return;

    const DISMISSED_KEY = 'a2hs_dismissed_until';
    const dismissedUntil = localStorage.getItem(DISMISSED_KEY);
    if (dismissedUntil && Date.now() < parseInt(dismissedUntil, 10)) return;

    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showBanner();
    });

    window.addEventListener('appinstalled', hideBanner);

    function showBanner() {
        if (document.getElementById('a2hs-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'a2hs-banner';
        banner.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
                <img src="/favicon.png" style="width:40px;height:40px;border-radius:10px;flex-shrink:0" alt="">
                <div style="min-width:0">
                    <p style="margin:0;font-size:13px;font-weight:700;color:#f1f5f9;line-height:1.2">Instale o BolãoVF</p>
                    <p style="margin:0;font-size:11px;color:#94a3b8;margin-top:2px;line-height:1.3">Acesse como app nativo, sem o browser</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0">
                <button id="a2hs-dismiss" style="padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#94a3b8;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">Agora não</button>
                <button id="a2hs-install" style="padding:6px 14px;border-radius:8px;border:none;background:#f5a623;color:#000;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">Instalar</button>
            </div>
        `;

        Object.assign(banner.style, {
            position: 'fixed',
            left: '12px',
            right: '12px',
            bottom: '80px',
            zIndex: '9999',
            display: 'flex',
            alignItems: 'center',
            gap: '12px',
            padding: '12px 14px',
            borderRadius: '14px',
            background: '#1c2230',
            border: '1px solid rgba(245,166,35,0.28)',
            boxShadow: '0 8px 32px rgba(0,0,0,0.55)',
            transition: 'opacity 0.25s ease, transform 0.25s ease',
            opacity: '0',
            transform: 'translateY(10px)',
        });

        document.body.appendChild(banner);

        requestAnimationFrame(() => requestAnimationFrame(() => {
            banner.style.opacity = '1';
            banner.style.transform = 'translateY(0)';
        }));

        document.getElementById('a2hs-install').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            hideBanner();
        });

        document.getElementById('a2hs-dismiss').addEventListener('click', () => {
            localStorage.setItem(DISMISSED_KEY, String(Date.now() + 7 * 24 * 60 * 60 * 1000));
            hideBanner();
        });
    }

    function hideBanner() {
        const banner = document.getElementById('a2hs-banner');
        if (!banner) return;
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(10px)';
        setTimeout(() => banner.remove(), 250);
    }
})();

// Connectivity banner
const updateConnectionBanner = () => {
    let banner = document.getElementById('pwa-connection-banner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'pwa-connection-banner';
        Object.assign(banner.style, {
            position: 'fixed',
            left: '50%',
            top: '12px',
            transform: 'translateX(-50%)',
            padding: '6px 14px',
            borderRadius: '999px',
            fontSize: '12px',
            fontWeight: '600',
            zIndex: '9998',
            transition: 'opacity 0.2s ease',
            pointerEvents: 'none',
        });
        document.body.appendChild(banner);
    }

    if (navigator.onLine) {
        banner.textContent = 'Online';
        banner.style.background = '#166534';
        banner.style.color = '#dcfce7';
        banner.style.opacity = '1';
        setTimeout(() => { banner.style.opacity = '0'; }, 1500);
    } else {
        banner.textContent = 'Sem conexão';
        banner.style.background = '#991b1b';
        banner.style.color = '#fee2e2';
        banner.style.opacity = '1';
    }
};

window.addEventListener('online', updateConnectionBanner);
window.addEventListener('offline', updateConnectionBanner);
window.addEventListener('online', () => { checkBuildSignature(); });
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') checkBuildSignature();
});
setInterval(() => {
    if (!navigator.onLine) return;
    checkBuildSignature();
}, 120_000);
checkBuildSignature();

// Vue app
const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#pwa-app');
