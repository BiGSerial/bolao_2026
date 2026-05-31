import client from './client';

export const getPools = () => client.get('/pools');
export const getPool = (id) => client.get(`/pools/${id}`);
export const createPool = (payload) => client.post('/pools', payload);
export const updatePool = (id, payload) => client.patch(`/pools/${id}`, payload);
export const deletePool = (id) => client.delete(`/pools/${id}`);
export const joinPool = (id, sector = null) =>
    client.post(`/pools/${id}/join-requests`, sector ? { sector } : {});
export const joinPoolByCode = (invite_code, sector = null) =>
    client.post('/pools/join-by-code', sector ? { invite_code, sector } : { invite_code });
export const leavePool = (id) => client.post(`/pools/${id}/leave`);
export const acceptInvite = (token) =>
    client.post(`/pools/invites/${token}/accept`);
export const finalizePool = (id) => client.post(`/pools/${id}/finalize`);
