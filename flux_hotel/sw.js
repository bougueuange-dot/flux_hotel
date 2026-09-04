const CACHE_NAME = 'hotel-flow-v1';

const FILES_TO_CACHE = [
    '/flux_hotel/',
    '/flux_hotel/manifest.json'
];

/*
|--------------------------------------------------------------------------
| Installation
|--------------------------------------------------------------------------
*/

self.addEventListener('install', event => {

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(FILES_TO_CACHE))
            .then(() => self.skipWaiting())
    );

});


/*
|--------------------------------------------------------------------------
| Activation
|--------------------------------------------------------------------------
*/

self.addEventListener('activate', event => {

    event.waitUntil(
        self.clients.claim()
    );

});


/*
|--------------------------------------------------------------------------
| Requêtes
|--------------------------------------------------------------------------
*/

self.addEventListener('fetch', event => {

    event.respondWith(

        fetch(event.request)
            .catch(() => caches.match(event.request))

    );

});
