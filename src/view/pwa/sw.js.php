// Workout service worker – version <?= VERSION ?> (rendered by the controller, cache name changes per deploy)
//
// Strategy:
//   /css /js /img, manifest   cache-first    (URLs are versioned with ?v=<commit>, so they are immutable)
//   HTML pages (GET)          network-first, cache fallback – the last seen pages stay readable offline
//   everything else           network only   (the plan JSON, the log POST)
// Also required for Chrome's install prompt (needs a fetch handler). No background sync, no precache.
const CACHE = 'workout-<?= VERSION ?>';
const OFFLINE_HTML = `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline · Workout</title><style>body{font-family:system-ui,sans-serif;background:#0b1120;color:#e9edf6;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center;padding:24px}p{color:#94a0b8}</style></head><body><div><h1>Du bist offline</h1><p>Diese Seite wurde noch nicht geladen. Sobald wieder Netz da ist, lädt sie automatisch.</p></div></body></html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter((name) => name !== CACHE).map((name) => caches.delete(name)));
        await self.clients.claim();
    })());
});

const isPageRequest = (request) =>
    request.mode === 'navigate' || request.destination === 'document' || (request.headers.get('Accept') || '').includes('text/html');

const isAsset = (pathname) =>
    pathname.startsWith('/css/') || pathname.startsWith('/js/') || pathname.startsWith('/img/') || pathname === '/manifest.webmanifest';

const isPage = (pathname) => pathname === '/' || pathname === '/workout' || pathname === '/logs';

self.addEventListener('fetch', (event) => {
    const {request} = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (isAsset(url.pathname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    if (isPageRequest(request) && isPage(url.pathname)) {
        event.respondWith(networkFirst(request));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
}

async function networkFirst(request) {
    const cache = await caches.open(CACHE);
    try {
        const response = await fetch(request);
        // only final 200 pages are worth keeping (a 401 from basic auth or an error page must not shadow the list)
        if (response.ok && !response.redirected) cache.put(request, response.clone());
        return response;
    } catch (error) {
        const cached = await cache.match(request);
        if (cached) return cached;
        return new Response(OFFLINE_HTML, {status: 503, headers: {'Content-Type': 'text/html; charset=UTF-8'}});
    }
}
