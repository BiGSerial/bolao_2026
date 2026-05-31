// Compat shim for legacy service worker manifests that still reference this file.
// Safe to keep during rollout to avoid install failures on old cached SW versions.
if (typeof self !== 'undefined' && typeof self.addEventListener === 'function') {
    self.addEventListener('install', () => {});
}
