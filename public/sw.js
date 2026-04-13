const CACHE_NAME = 'amharic-practice-v1';
const ASSETS_TO_CACHE = [
    '/',
    '/css/amharic-practice.css',
    '/js/amharic-practice.js',
    '/manifest.json',
    '/images/icons/icon-192.png',
    '/images/icons/icon-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(ASSETS_TO_CACHE))
    );
});

self.addEventListener('fetch', event => {
    // For API calls and external resources, we don't cache for now
    if (event.request.url.includes('/api/')) {
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then(response => response || fetch(event.request))
    );
});
