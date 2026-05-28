import client from './client';

export const getNotifications = (params = {}) =>
    client.get('/notifications', { params });

export const markAsRead = (id) =>
    client.patch(`/notifications/${id}/read`);
