import axios from 'axios';

const client = axios.create({
    baseURL: '/api/v1',
    headers: { Accept: 'application/json' },
});

client.interceptors.request.use((config) => {
    const token = sessionStorage.getItem('pwa_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

client.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            sessionStorage.removeItem('pwa_token');
            window.location.href = '/pwa/login';
        }
        return Promise.reject(err);
    },
);

export default client;
