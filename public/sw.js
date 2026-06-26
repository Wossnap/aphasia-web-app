const CACHE_NAME = 'amharic-practice-v3';

// Static assets to cache (JS/CSS have content hashes, so they're safe to cache aggressively)
const ASSETS_TO_CACHE = [
    '/manifest.json',
    '/images/icons/icon-192.png',
    '/images/icons/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS_TO_CACHE))
    );
    // Take control immediately without waiting for old SW to die
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    // Delete all old caches so the old HTML is gone
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// Only full, basic 200 responses can be stored in the Cache API.
// Range requests return 206 (Partial Content), which throws on cache.put.
function cacheableResponse(response) {
    return response &&
        response.status === 200 &&
        response.type !== 'opaqueredirect';
}

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // API calls: always network, never cache
    if (url.pathname.startsWith('/api/')) return;

    // Range requests (audio/video seeking) must go straight to the network
    // so the browser receives the 206 partial response it expects.
    if (event.request.headers.has('range')) return;

    // Audio/GIF/image files: cache-first (they don't change once uploaded)
    if (url.pathname.startsWith('/audio/') ||
        url.pathname.startsWith('/gifs/')  ||
        url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.match(event.request).then(cached =>
                cached || fetch(event.request).then(response => {
                    if (cacheableResponse(response)) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                })
            )
        );
        return;
    }

    // Vite-built assets (content-hashed filenames): cache-first
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(event.request).then(cached =>
                cached || fetch(event.request).then(response => {
                    if (cacheableResponse(response)) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                })
            )
        );
        return;
    }

    // HTML pages (including /): network-first so updates always show immediately
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
