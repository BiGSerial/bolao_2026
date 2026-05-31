// Compat shim for legacy service worker precache entries (camelCase filename).
if (typeof self !== 'undefined' && typeof self.addEventListener === 'function') {
    self.addEventListener('install', () => {});
}
