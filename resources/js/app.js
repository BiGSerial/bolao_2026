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
