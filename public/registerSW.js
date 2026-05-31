// Compat shim for legacy service worker precache entries (exact case: registerSW.js).
if (typeof self !== 'undefined' && typeof self.addEventListener === 'function') {
    self.addEventListener('install', () => {});
}
