import client from './client';

export const getLegalDocument = (type) =>
    client.get(`/legal/${type}`);

export const acceptLegalDocuments = (documentIds) =>
    client.post('/me/legal/accept', { document_ids: documentIds });

export const getLegalPending = () =>
    client.get('/me/legal/pending');
