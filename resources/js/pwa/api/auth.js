import client from './client';

export const login = (login, password) =>
    client.post('/auth/login', { login, password, device_name: 'pwa' });

export const logout = () => client.post('/auth/logout');

export const me = () => client.get('/me');

export const appConfig = () => client.get('/app-config');
