import { ref, onMounted } from 'vue';
import * as notificationsApi from '../api/notifications';

export function usePushNotifications() {
    const isSupported = ref('serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
    const permission = ref(isSupported.value ? Notification.permission : 'denied');
    const isSubscribed = ref(false);
    const loading = ref(false);
    const lastError = ref('');

    function base64UrlToUint8Array(base64Url) {
        const padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
        const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; i += 1) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    async function checkSubscription() {
        if (!isSupported.value) return;
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        isSubscribed.value = !!subscription;
    }

    async function requestPermission() {
        if (!isSupported.value) return false;
        const result = await Notification.requestPermission();
        permission.value = result;
        return result === 'granted';
    }

    async function subscribe() {
        if (!isSupported.value) return;
        loading.value = true;
        lastError.value = '';
        try {
            if (!window.isSecureContext) {
                throw new Error('Push requer contexto seguro (HTTPS/localhost).');
            }

            const hasPermission = permission.value === 'granted' || await requestPermission();
            if (!hasPermission) throw new Error('Permission denied');

            const registration = await navigator.serviceWorker.ready;
            const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
            
            if (!vapidPublicKey) {
                throw new Error('VITE_VAPID_PUBLIC_KEY não configurada.');
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(vapidPublicKey)
            });

            await notificationsApi.subscribeToPush(subscription);
            isSubscribed.value = true;
        } catch (error) {
            lastError.value = error instanceof Error ? error.message : 'Falha ao ativar notificações push.';
            console.error('Erro ao subscrever para push:', error);
        } finally {
            loading.value = false;
        }
    }

    async function unsubscribe() {
        if (!isSupported.value) return;
        loading.value = true;
        lastError.value = '';
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await notificationsApi.unsubscribeFromPush(subscription.endpoint);
                await subscription.unsubscribe();
            }
            isSubscribed.value = false;
        } catch (error) {
            console.error('Erro ao cancelar push:', error);
        } finally {
            loading.value = false;
        }
    }

    onMounted(() => {
        checkSubscription();
    });

    return {
        isSupported,
        permission,
        isSubscribed,
        loading,
        lastError,
        requestPermission,
        subscribe,
        unsubscribe
    };
}
