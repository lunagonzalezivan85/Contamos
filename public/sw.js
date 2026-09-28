/* ==========================================================================
   Service Worker — Portal de gestores (PWA)
   - Assets estáticos: cache-first
   - Navegación (páginas): network-first con fallback a caché
   ========================================================================== */
const CACHE = 'cfsi-portal-v1';
const STATIC_EXT = /\.(css|js|png|jpe?g|svg|webp|gif|woff2?|webmanifest)$/i;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Estáticos — cache primero, red como respaldo
    if (STATIC_EXT.test(url.pathname)) {
        e.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, copy));
                }
                return res;
            }))
        );
        return;
    }

    // Páginas — red primero, caché como respaldo offline
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, copy));
                }
                return res;
            }).catch(() => caches.match(req))
        );
    }
});
