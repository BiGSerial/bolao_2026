import client from './client';

export const getNotifications = (params = {}) =>
    client.get('/notifications', { params });

export const markAsRead = (id) =>
    client.patch(`/notifications/${id}/read`);

export const subscribeToPush = (subscription) =>
    client.post('/notifications/subscriptions', {
        endpoint: subscription.endpoint,
        public_key: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))),
        auth_token: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth')))),
    });

export const unsubscribeFromPush = (endpoint) =>
    client.delete('/notifications/subscriptions', { data: { endpoint } });
