import client from './client';

export const getMatches = (params = {}) => client.get('/matches', { params });
