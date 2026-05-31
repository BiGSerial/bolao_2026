import axios from 'axios';
import { clearStoredToken, getStoredToken } from '../utils/authToken';

const client = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

client.interceptors.request.use((config) => {
    const token = getStoredToken();
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

client.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            clearStoredToken();
            window.location.href = '/pwa/login';
        }
        return Promise.reject(err);
    },
);

export default client;
