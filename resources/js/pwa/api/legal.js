import client from './client';

export const getLegalDocument = (type) =>
    client.get(`/v1/legal/${type}`);

export const acceptLegalDocuments = (documentIds) =>
    client.post('/v1/me/legal/accept', { document_ids: documentIds });

export const getLegalPending = () =>
    client.get('/v1/me/legal/pending');
