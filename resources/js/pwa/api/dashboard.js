import client from './client';

export const getDashboard = (params = {}) => client.get('/dashboard', { params });
