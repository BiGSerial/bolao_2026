import { ref, onMounted } from 'vue';
import * as notificationsApi from '../api/notifications';

export function usePushNotifications() {
    const isSupported = ref('serviceWorker' in navigator && 'PushManager' in window);
    const permission = ref(Notification.permission);
    const isSubscribed = ref(false);
    const loading = ref(false);

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
        try {
            const hasPermission = permission.value === 'granted' || await requestPermission();
            if (!hasPermission) throw new Error('Permission denied');

            const registration = await navigator.serviceWorker.ready;
            const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
            
            if (!vapidPublicKey) {
                console.error('VITE_VAPID_PUBLIC_KEY não configurada.');
                return;
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidPublicKey
            });

            await notificationsApi.subscribeToPush(subscription);
            isSubscribed.value = true;
        } catch (error) {
            console.error('Erro ao subscrever para push:', error);
        } finally {
            loading.value = false;
        }
    }

    async function unsubscribe() {
        if (!isSupported.value) return;
        loading.value = true;
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
        requestPermission,
        subscribe,
        unsubscribe
    };
}
