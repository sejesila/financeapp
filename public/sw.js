// Fin Tracker — Service Worker
// Strategy:
//   Static assets  → Cache First (fast loads)
//   Pages/API      → Network First (fresh data), fallback to cache
//   Offline page   → shown when network + cache both fail

const CACHE_VERSION = 'v1';
const STATIC_CACHE  = `fin-tracker-static-${CACHE_VERSION}`;
const PAGES_CACHE   = `fin-tracker-pages-${CACHE_VERSION}`;
const ALL_CACHES    = [STATIC_CACHE, PAGES_CACHE];

// Shell assets to pre-cache on install
const STATIC_ASSETS = [
    '/offline.html',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/favicon.ico',
];

// ─── Install ────────────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// ─── Activate ───────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys
                    .filter(key => !ALL_CACHES.includes(key))
                    .map(key => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// ─── Fetch ──────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests and cross-origin requests
    if (request.method !== 'GET') return;
    if (url.origin !== location.origin) return;

    // Skip Laravel internals, hot-reload, and Vite dev server
    if (
        url.pathname.startsWith('/_debugbar') ||
        url.pathname.startsWith('/hot') ||
        url.pathname.includes('@vite') ||
        url.pathname.startsWith('/livewire') ||
        url.pathname.startsWith('/telescope')
    ) return;

    // Static assets (CSS, JS, images, fonts) → Cache First
    if (isStaticAsset(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // HTML pages → Network First
    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithOfflineFallback(request));
        return;
    }

    // Everything else → Network First
    event.respondWith(networkFirst(request, PAGES_CACHE));
});

// ─── Helpers ────────────────────────────────────────────────
function isStaticAsset(pathname) {
    return (
        pathname.startsWith('/build/') ||
        pathname.startsWith('/images/') ||
        pathname.startsWith('/icons/') ||
        /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|ico|webp)$/.test(pathname)
    );
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Asset not available offline.', { status: 503 });
    }
}

async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached || new Response('Offline', { status: 503 });
    }
}

async function networkFirstWithOfflineFallback(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(PAGES_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        const offline = await caches.match('/offline.html');
        return offline || new Response('<h1>You are offline</h1>', {
            headers: { 'Content-Type': 'text/html' }
        });
    }
}

// ─── Background Sync (future-proof) ─────────────────────────
self.addEventListener('sync', event => {
    if (event.tag === 'sync-transactions') {
        event.waitUntil(syncTransactions());
    }
});

async function syncTransactions() {
    // Placeholder: implement if you add IndexedDB offline queueing
    console.log('[SW] Background sync: transactions');
}
