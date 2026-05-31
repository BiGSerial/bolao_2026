import './bootstrap';
import './legal';
import '@fontsource/barlow/400.css';
import '@fontsource/barlow/500.css';
import '@fontsource/barlow/600.css';
import '@fontsource/barlow-condensed/600.css';
import '@fontsource/barlow-condensed/700.css';
import '@fontsource/barlow-condensed/800.css';
import '@fontsource/instrument-sans/400.css';
import '@fontsource/instrument-sans/500.css';
import '@fontsource/instrument-sans/600.css';
import '@tabler/icons-webfont/dist/tabler-icons.css';
import Swal from 'sweetalert2';
import { marked } from 'marked';
import Chart from 'chart.js/auto';
import { registerSW } from 'virtual:pwa-register';

const bolaoSwalDefaults = {
    background: '#13161b',
    color: '#e2e8f0',
    confirmButtonColor: '#f5a623',
    cancelButtonColor: '#252b38',
    customClass: {
        popup: 'border border-white/10 rounded-xl',
        confirmButton: 'font-semibold',
        cancelButton: 'font-semibold',
    },
};

const originalSwalFire = Swal.fire.bind(Swal);
Swal.fire = (options = {}, ...rest) => {
    const merged = typeof options === 'object' && options !== null
        ? {
              ...bolaoSwalDefaults,
              ...options,
              customClass: {
                  ...(bolaoSwalDefaults.customClass ?? {}),
                  ...(options.customClass ?? {}),
              },
          }
        : options;

    return originalSwalFire(merged, ...rest);
};

window.Swal = Swal;
window.marked = marked;
window.Chart = Chart;

const isPwaRoute = (() => {
    const path = window.location.pathname.replace(/\/+$/, '') || '/';
    return path === '/pwa' || path.startsWith('/pwa/');
})();

if (isPwaRoute) {
    registerSW({ immediate: true });
}

const updateConnectionBanner = () => {
    let banner = document.getElementById('connection-status-banner');
    const isOnline = navigator.onLine;

    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'connection-status-banner';
        banner.style.position = 'fixed';
        banner.style.left = '50%';
        banner.style.bottom = '16px';
        banner.style.transform = 'translateX(-50%)';
        banner.style.padding = '8px 12px';
        banner.style.borderRadius = '999px';
        banner.style.fontSize = '12px';
        banner.style.fontWeight = '600';
        banner.style.zIndex = '9999';
        banner.style.transition = 'opacity 0.2s ease';
        banner.dataset.lastStatus = isOnline ? 'online' : 'offline';
        document.body.appendChild(banner);
    }

    const previousStatus = banner.dataset.lastStatus || '';

    if (isOnline) {
        // Evita toast de "Online" ao trocar de aba/foco sem mudança real de estado.
        if (previousStatus === 'online') {
            return;
        }
        banner.textContent = 'Online';
        banner.style.background = '#166534';
        banner.style.color = '#dcfce7';
        banner.style.opacity = '1';
        banner.dataset.lastStatus = 'online';
        setTimeout(() => {
            if (banner) {
                banner.style.opacity = '0';
            }
        }, 1200);
    } else {
        banner.textContent = 'Sem conexão';
        banner.style.background = '#991b1b';
        banner.style.color = '#fee2e2';
        banner.style.opacity = '1';
        banner.dataset.lastStatus = 'offline';
    }
};

window.addEventListener('online', updateConnectionBanner);
window.addEventListener('offline', updateConnectionBanner);
window.addEventListener('load', updateConnectionBanner);

(function () {
    // Banner "Instale o app" na versão web normal.
    // Não depende de beforeinstallprompt (que só dispara se o SW estiver registrado
    // na rota atual). Exibe após breve delay com fallback para abrir /pwa.
    // A rota /pwa usa implementação própria no entry da PWA.
    if (isPwaRoute) return;
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) return;

    const DISMISSED_KEY = 'a2hs_dismissed_until';
    const dismissedUntil = localStorage.getItem(DISMISSED_KEY);
    if (dismissedUntil && Date.now() < parseInt(dismissedUntil, 10)) return;

    let deferredPrompt = null;
    let bannerMounted = false;

    // Captura o prompt nativo se disponível (Chrome/Edge Android)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Atualiza o botão se o banner já estiver visível
        const btn = document.getElementById('a2hs-install-btn');
        if (btn) btn.textContent = 'Instalar';
    });

    window.addEventListener('appinstalled', hideA2HSBanner);

    // Mostra após 2s — sem depender do evento nativo que pode nunca disparar
    // em rotas web onde o SW não está registrado ainda.
    setTimeout(() => { if (!bannerMounted) showA2HSBanner(); }, 2000);

    function showA2HSBanner() {
        if (document.getElementById('a2hs-banner')) return;
        bannerMounted = true;

        // Na versão web mobile a nav bar ocupa 68px na base; posiciona acima dela.
        const isMobile = window.matchMedia('(max-width: 767px)').matches;
        const bottomOffset = isMobile
            ? 'calc(76px + env(safe-area-inset-bottom, 0px))'
            : 'calc(16px + env(safe-area-inset-bottom, 0px))';

        const banner = document.createElement('div');
        banner.id = 'a2hs-banner';
        banner.innerHTML = `
            <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
                <img src="/favicon.png" style="width:40px;height:40px;border-radius:10px;flex-shrink:0" alt="">
                <div style="min-width:0">
                    <p style="margin:0;font-size:13px;font-weight:700;color:#f1f5f9;line-height:1.2">BolãoVF tem versão app</p>
                    <p style="margin:0;font-size:11px;color:#94a3b8;margin-top:2px;line-height:1.3">Notificações, modo offline e experiência nativa</p>
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0">
                <button id="a2hs-dismiss" style="padding:6px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#94a3b8;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit">Não</button>
                <button id="a2hs-install-btn" style="padding:6px 14px;border-radius:8px;border:none;background:#f5a623;color:#000;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">${deferredPrompt ? 'Instalar' : 'Abrir app'}</button>
            </div>
        `;

        Object.assign(banner.style, {
            position: 'fixed',
            left: '12px',
            right: '12px',
            bottom: bottomOffset,
            zIndex: '9050',
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

        document.getElementById('a2hs-install-btn').addEventListener('click', async () => {
            if (deferredPrompt) {
                // Instala nativamente via browser prompt
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                if (outcome === 'accepted') { hideA2HSBanner(); return; }
            }
            // Fallback: abre a PWA no browser (usuário pode instalar de lá)
            window.open('/pwa', '_blank', 'noopener');
            hideA2HSBanner();
        });

        document.getElementById('a2hs-dismiss').addEventListener('click', () => {
            // Suprime por 7 dias
            localStorage.setItem(DISMISSED_KEY, String(Date.now() + 7 * 24 * 60 * 60 * 1000));
            hideA2HSBanner();
        });
    }

    function hideA2HSBanner() {
        const banner = document.getElementById('a2hs-banner');
        if (!banner) return;
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(10px)';
        setTimeout(() => banner.remove(), 250);
    }
})();

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const action = form.getAttribute('action') ?? '';
    if (!action.includes('/logout')) {
        return;
    }

    if ('caches' in window) {
        caches.keys().then((keys) => Promise.all(keys.map((key) => caches.delete(key))));
    }
});
