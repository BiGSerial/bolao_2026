import client from './client';

export const getPoolPredictions = (poolId, params = {}) =>
    client.get(`/pools/${poolId}/predictions/me`, { params });

export const getPrediction = (poolId, matchId) =>
    client.get(`/pools/${poolId}/matches/${matchId}/predictions/me`);

export const savePrediction = (poolId, matchId, homeScore, awayScore) =>
    client.put(`/pools/${poolId}/matches/${matchId}/predictions/me`, {
        home_score: homeScore,
        away_score: awayScore,
    });
