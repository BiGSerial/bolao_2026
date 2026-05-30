import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function getPwaEcho() {
    if (echoInstance) return echoInstance;

    const token = sessionStorage.getItem('pwa_token');
    if (!token) return null;

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/api/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
    });

    return echoInstance;
}

export function disconnectPwaEcho() {
    if (!echoInstance) return;
    echoInstance.disconnect();
    echoInstance = null;
}
