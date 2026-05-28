import client from './client';

export const getRanking = (poolId) => client.get(`/pools/${poolId}/rankings`);
export const getLiveRanking = (poolId) => client.get(`/pools/${poolId}/rankings/live`);
