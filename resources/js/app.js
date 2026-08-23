import './bootstrap';

// Converts a URL-safe base64 VAPID public key into the Uint8Array shape
// PushManager.subscribe({ applicationServerKey }) requires.
window.urlBase64ToUint8Array = function (base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
};

// ── PWA: register the admin service worker ───────────────────────────────────
// Only on /admin/* pages and only in a secure context (HTTPS or localhost) —
// a plain-HTTP dev host can't register a service worker, so we skip it there.
if (
    'serviceWorker' in navigator &&
    window.isSecureContext &&
    window.location.pathname.startsWith('/admin')
) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/admin/sw.js', { scope: '/admin/' })
            .catch(() => {
                /* registration is best-effort; the app works without it */
            });
    });
}
