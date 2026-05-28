import client from './client';

export const getPoolMembers = (poolId) => client.get(`/pools/${poolId}/members`);
export const updatePoolMember = (poolId, memberId, payload) =>
    client.patch(`/pools/${poolId}/members/${memberId}`, payload);
export const removePoolMember = (poolId, memberId) =>
    client.delete(`/pools/${poolId}/members/${memberId}`);
export const inviteToPool = (poolId, payload) =>
    client.post(`/pools/${poolId}/invites`, payload);

