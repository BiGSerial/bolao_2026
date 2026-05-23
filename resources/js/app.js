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

registerSW({ immediate: true });

const updateConnectionBanner = () => {
    let banner = document.getElementById('connection-status-banner');

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
        document.body.appendChild(banner);
    }

    if (navigator.onLine) {
        banner.textContent = 'Online';
        banner.style.background = '#166534';
        banner.style.color = '#dcfce7';
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
    }
};

window.addEventListener('online', updateConnectionBanner);
window.addEventListener('offline', updateConnectionBanner);
window.addEventListener('load', updateConnectionBanner);

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
