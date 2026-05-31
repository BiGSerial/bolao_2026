import client from './client';

export const getNotifications = (params = {}) =>
    client.get('/notifications', { params });

export const markAsRead = (id) =>
    client.patch(`/notifications/${id}/read`);

export const subscribeToPush = (subscription) =>
{
    const json = subscription.toJSON();
    const keys = json.keys ?? {};

    return client.post('/notifications/subscriptions', {
        endpoint: subscription.endpoint,
        public_key: keys.p256dh ?? null,
        auth_token: keys.auth ?? null,
        content_encoding: json?.contentEncoding ?? 'aesgcm',
    });
};

export const unsubscribeFromPush = (endpoint) =>
    client.delete('/notifications/subscriptions', { data: { endpoint } });
