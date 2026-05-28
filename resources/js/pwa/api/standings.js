import client from './client';

export const getStandings = (params = {}) => client.get('/standings', { params });

