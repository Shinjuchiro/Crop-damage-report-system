/*
 |-----------------------------------------------------------------------------
 | Service worker
 |-----------------------------------------------------------------------------
 | This is what makes the system installable on a phone and what keeps it from
 | showing a blank browser error page when a farmer loses signal in the field.
 |
 | The rules are deliberately conservative:
 |
 |   Static files (build assets, icons, photos, the barangay boundaries)
 |       Cache first. They are versioned or they never change, so serving them
 |       from the phone is safe and makes the app open quickly.
 |
 |   Pages and data
 |       Network only, with an offline page as the fallback. Damage reports,
 |       validation statuses and assistance records must never be shown from a
 |       stale copy, and a logged in page should not sit in a cache on a shared
 |       phone. If the network is gone the farmer is told plainly.
 |
 |   Anything that is not a GET
 |       Left completely alone. Submitting a report always goes to the server.
 |
 | Bump CACHE_VERSION whenever this file or the offline page changes so old
 | caches are cleared on the next visit.
 */

const CACHE_VERSION = 'v3';
const STATIC_CACHE  = 'tanza-static-' + CACHE_VERSION;
const OFFLINE_URL   = '/offline.html';

/* Files worth having on the phone before the first offline moment. */
const PRECACHE = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/images/tanza-seal.png',
];

/* Paths that must always hit the network, even for GET. */
const NEVER_CACHE = [
    '/login',
    '/logout',
    '/register',
    '/auth/',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            // A missing file must not stop the worker from installing.
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== STATIC_CACHE).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

/* Lets the page tell a waiting worker to take over straight away. */
self.addEventListener('message', (event) => {
    if (event.data === 'skip-waiting') {
        self.skipWaiting();
    }
});

function isStaticAsset(url) {
    return url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/images/')
        || url.pathname.startsWith('/geo/')
        || url.pathname === '/manifest.webmanifest';
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Never touch writes. A submitted damage report goes straight to the server.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Other origins (map tiles, CDN libraries) are left to the browser.
    if (url.origin !== self.location.origin) {
        return;
    }

    if (NEVER_CACHE.some((path) => url.pathname.startsWith(path))) {
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((hit) => {
                if (hit) {
                    return hit;
                }

                return fetch(request).then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }

                    return response;
                });
            })
        );

        return;
    }

    // Pages: always fresh, or a clear offline message.
    //
    // This used to fetch with { redirect: 'manual' } to work around an
    // installed-app splash-screen hang, on the theory that start_url's
    // redirect (see manifest.webmanifest) wasn't completing for a WebAPK.
    // That turned out to cause a worse problem - ERR_TOO_MANY_REDIRECTS in
    // ordinary browser tabs, not just the installed app - so it's reverted
    // back to plain fetch(), which is what every browser has always handled
    // correctly for a normal top-level navigation. The splash-hang case is
    // now handled the safer way instead: manifest.webmanifest's start_url
    // points straight at /login, so the common case (a guest opening the
    // installed app) never redirects through the service worker at all.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
    }
});
