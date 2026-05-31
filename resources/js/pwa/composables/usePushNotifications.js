import { ref, onMounted } from 'vue';
import * as notificationsApi from '../api/notifications';

export function usePushNotifications() {
    const isSupported = ref(
        'serviceWorker' in navigator &&
        'PushManager'   in window    &&
        'Notification'  in window,
    );
    const permission   = ref(isSupported.value ? Notification.permission : 'denied');
    const isSubscribed = ref(false);
    const loading      = ref(false);
    const lastError    = ref('');

    // ── Helpers ─────────────────────────────────────────────────────────────────

    function base64UrlToUint8Array(base64Url) {
        const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
        const base64  = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw     = atob(base64);
        const out     = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    // Wrapper com timeout para navigator.serviceWorker.ready.
    // Sem ele, a promise pode nunca resolver se o SW estiver em estado inválido.
    function swReady(timeoutMs = 10000) {
        return new Promise((resolve, reject) => {
            const timer = setTimeout(
                () => reject(new Error('O service worker não respondeu a tempo. Recarregue a página.')),
                timeoutMs,
            );
            navigator.serviceWorker.ready
                .then(reg => { clearTimeout(timer); resolve(reg); })
                .catch(err => { clearTimeout(timer); reject(err); });
        });
    }

    // ── State check ─────────────────────────────────────────────────────────────

    async function checkSubscription() {
        if (!isSupported.value) return;
        // Sincroniza permissão com o estado real do browser
        permission.value = Notification.permission;
        try {
            const reg = await swReady(5000);
            const sub = await reg.pushManager.getSubscription();
            isSubscribed.value = !!sub;
        } catch {
            // SW não pronto ainda — não é erro fatal no mount
        }
    }

    // ── Subscribe ───────────────────────────────────────────────────────────────

    async function subscribe() {
        if (!isSupported.value) return;
        loading.value   = true;
        lastError.value = '';

        try {
            // 1. Contexto seguro
            if (!window.isSecureContext) {
                throw new Error('Push requer HTTPS ou localhost.');
            }

            // 2. VAPID configurada — checa ANTES de pedir permissão ao usuário
            const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
            if (!vapidKey) {
                throw new Error('Notificações push não estão configuradas no servidor.');
            }

            // 3. Permissão do browser
            permission.value = Notification.permission;   // sincroniza antes de checar
            if (permission.value !== 'granted') {
                const result = await Notification.requestPermission();
                permission.value = result;
                if (result !== 'granted') {
                    throw new Error(
                        result === 'denied'
                            ? 'Permissão negada. Habilite notificações nas configurações do browser.'
                            : 'Permissão não concedida.',
                    );
                }
            }

            // 4. Service Worker pronto (com timeout)
            const registration = await swReady();

            // 5. Inscrever no PushManager
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly:   true,
                applicationServerKey: base64UrlToUint8Array(vapidKey),
            });

            // 6. Enviar endpoint ao servidor
            await notificationsApi.subscribeToPush(subscription);
            isSubscribed.value = true;

        } catch (err) {
            // Resincroniza permissão — o browser pode ter mudado durante o fluxo
            if ('Notification' in window) permission.value = Notification.permission;

            const msg = err instanceof Error ? err.message : 'Falha ao ativar notificações push.';
            lastError.value = msg;
            console.error('[push] subscribe error:', err);
        } finally {
            loading.value = false;
        }
    }

    // ── Unsubscribe ──────────────────────────────────────────────────────────────

    async function unsubscribe() {
        if (!isSupported.value) return;
        loading.value   = true;
        lastError.value = '';
        try {
            const reg = await swReady();
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                await notificationsApi.unsubscribeFromPush(sub.endpoint);
                await sub.unsubscribe();
            }
            isSubscribed.value = false;
        } catch (err) {
            lastError.value = err instanceof Error ? err.message : 'Falha ao desativar notificações.';
            console.error('[push] unsubscribe error:', err);
        } finally {
            loading.value = false;
        }
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────────

    onMounted(() => {
        checkSubscription();
    });

    return {
        isSupported,
        permission,
        isSubscribed,
        loading,
        lastError,
        subscribe,
        unsubscribe,
    };
}
