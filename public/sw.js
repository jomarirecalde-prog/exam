/* eslint-disable no-restricted-globals */
const CACHE_VERSION = 'exam-system-v2';
const SHELL_CACHE = `${CACHE_VERSION}-shell`;
const ASSET_CACHE = `${CACHE_VERSION}-assets`;
const EXAM_CACHE = `${CACHE_VERSION}-exams`;

const CORE_SHELL_URLS = [
    '/',
    '/login',
    '/dashboard',
    '/examinations',
    '/offline/app',
    '/offline/sync',
    '/manifest.webmanifest',
];

const CACHEABLE_EXTENSIONS = ['.css', '.js', '.woff', '.woff2', '.svg', '.png', '.jpg', '.webp', '.ico'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(CORE_SHELL_URLS))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => (
                        key.startsWith('exam-pwa-')
                        || key.startsWith('exam-system-')
                    ) && !key.startsWith(CACHE_VERSION))
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data?.type === 'CACHE_SHELL_URLS' && Array.isArray(event.data.urls)) {
        event.waitUntil(cacheShellUrls(event.data.urls));
    }
    if (event.data?.type === 'CACHE_EXAM_ASSETS' && Array.isArray(event.data.urls)) {
        event.waitUntil(cacheExamAssets(event.data.urls));
    }
    if (event.data?.type === 'CACHE_BUILD_ASSETS' && Array.isArray(event.data.urls)) {
        event.waitUntil(cacheBuildAssets(event.data.urls));
    }
});

async function cacheShellUrls(urls) {
    const cache = await caches.open(SHELL_CACHE);
    await Promise.allSettled(
        urls.filter(Boolean).map(async (url) => {
            try {
                const response = await fetch(url, { credentials: 'same-origin' });
                if (response.ok) {
                    await cache.put(url, response);
                }
            } catch {
                /* ignore */
            }
        }),
    );
}

async function cacheExamAssets(urls) {
    const cache = await caches.open(EXAM_CACHE);
    await Promise.allSettled(
        urls.filter(Boolean).map(async (url) => {
            try {
                const response = await fetch(url, { credentials: 'same-origin' });
                if (response.ok) {
                    await cache.put(url, response);
                }
            } catch {
                /* ignore */
            }
        }),
    );
}

async function cacheBuildAssets(urls) {
    const cache = await caches.open(ASSET_CACHE);
    await Promise.allSettled(
        urls.filter(Boolean).map(async (url) => {
            try {
                const response = await fetch(url, { credentials: 'include' });
                if (response.ok) {
                    await cache.put(url, response);
                }
            } catch {
                /* ignore */
            }
        }),
    );
}

function isCacheableAsset(url) {
    if (url.pathname.startsWith('/build/')) {
        return true;
    }
    if (url.pathname.startsWith('/vendor/') || url.pathname.startsWith('/livewire/')) {
        return true;
    }
    return CACHEABLE_EXTENSIONS.some((ext) => url.pathname.endsWith(ext));
}

function isSensitiveApi(url) {
    return url.pathname.includes('/attempts/')
        && (url.pathname.includes('/answers') || url.pathname.includes('/state') || url.pathname.includes('/sync'));
}

async function offlineShellFallback(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    const offlineApp = await caches.match('/offline/app');
    if (offlineApp) {
        return offlineApp;
    }

    const dashboard = await caches.match('/dashboard');
    if (dashboard) {
        return dashboard;
    }

    return caches.match('/');
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (isSensitiveApi(url)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => offlineShellFallback(request)),
        );
        return;
    }

    if (isCacheableAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    if (navigator.onLine) {
                        fetch(request).then((response) => {
                            if (response.ok) {
                                caches.open(ASSET_CACHE).then((cache) => cache.put(request, response));
                            }
                        }).catch(() => {});
                    }
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                }).catch(() => cached);
            }),
        );
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === 'exam-sync') {
        event.waitUntil(notifyClientsToSync());
    }
});

async function notifyClientsToSync() {
    const clients = await self.clients.matchAll({ type: 'window' });
    clients.forEach((client) => client.postMessage({ type: 'TRIGGER_SYNC' }));
}
