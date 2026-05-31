const TOKEN_KEY = 'pwa_token';
const PERSIST_KEY = 'pwa_auth_persist';

export function getStoredToken() {
    return sessionStorage.getItem(TOKEN_KEY) || localStorage.getItem(TOKEN_KEY) || null;
}

export function setStoredToken(token, persist = false) {
    if (!token) return;
    if (persist) {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(PERSIST_KEY, '1');
        sessionStorage.removeItem(TOKEN_KEY);
    } else {
        sessionStorage.setItem(TOKEN_KEY, token);
        localStorage.removeItem(TOKEN_KEY);
        localStorage.setItem(PERSIST_KEY, '0');
    }
}

export function clearStoredToken() {
    sessionStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(PERSIST_KEY);
}

export function isTokenPersistent() {
    return localStorage.getItem(PERSIST_KEY) === '1';
}
