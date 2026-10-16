/*
 * meSync service worker.
 *
 * Only static files are cached (build assets, icons, the offline page). Pages and
 * Inertia responses always come from the network: they contain private business
 * data and CSRF tokens, so they must never be served from a cache.
 */

const VERSION = 'mesync-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/images/pwa/icon-192.png', '/images/logo/logo-512x512-dark.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)));
    self.skipWaiting();
});

// Drop caches of older versions (also the ones of the former laravelpwa worker)
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    // Page loads: always the network, the offline page when there is none
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Vite build files have a hash in the name and never change: cache first
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(VERSION).then((cache) => cache.put(request, copy));
                }
                return response;
            })),
        );
        return;
    }

    // Static images: from the cache right away, refreshed in the background
    if (url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.open(VERSION).then((cache) => cache.match(request).then((cached) => {
                const network = fetch(request).then((response) => {
                    if (response.ok) cache.put(request, response.clone());
                    return response;
                }).catch(() => cached);

                return cached || network;
            })),
        );
    }

    // Everything else (Inertia requests, PDFs, downloads, uploads) goes straight to the network
});
