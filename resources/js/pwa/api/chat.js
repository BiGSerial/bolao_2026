import client from './client';

export const getChatMessages = (poolId, params = {}) =>
    client.get(`/pools/${poolId}/chat/messages`, { params });

export const getChatParticipants = (poolId) =>
    client.get(`/pools/${poolId}/chat/participants`);

export const sendChatMessage = (poolId, payload) =>
    client.post(`/pools/${poolId}/chat/messages`, payload);

export const toggleChatReaction = (poolId, messageId, emoji) =>
    client.post(`/pools/${poolId}/chat/messages/${messageId}/reactions`, { emoji });

export const setChatTyping = (poolId, typing) =>
    client.post(`/pools/${poolId}/chat/typing`, { typing });

export const markChatRead = (poolId, lastReadMessageId) =>
    client.post(`/pools/${poolId}/chat/read`, { last_read_message_id: lastReadMessageId });
