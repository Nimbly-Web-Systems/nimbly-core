const worker_url = new URL(self.location.href);
const version = worker_url.searchParams.get('v') || '1';
const scope_url = self.registration.scope;
const scope_key = new URL(scope_url).pathname
  .replace(/[^a-z0-9]+/gi, '-')
  .replace(/^-+|-+$/g, '') || 'root';
const cache_prefix = `nimbly-static-${scope_key}-`;
const cache_name = `${cache_prefix}${version}`;
const assets = [
  `app.css?v=${encodeURIComponent(version)}`,
  `app.js?v=${encodeURIComponent(version)}`,
  `favicon.ico?v=${encodeURIComponent(version)}`,
  `favicon.svg?v=${encodeURIComponent(version)}`,
  `favicon-32x32.png?v=${encodeURIComponent(version)}`,
  `apple-touch-icon.png?v=${encodeURIComponent(version)}`,
  'pwa-icon-192.png',
  'pwa-icon-512.png',
  'pwa-icon-maskable-512.png',
].map((path) => new URL(path, scope_url).href);
const asset_paths = new Set(assets.map((asset) => new URL(asset).pathname));

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(cache_name)
      .then((cache) => cache.addAll(assets))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith(cache_prefix) && key !== cache_name)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const request_url = new URL(event.request.url);
  if (request_url.origin !== self.location.origin || !asset_paths.has(request_url.pathname)) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then((cached) => cached || fetch(event.request))
  );
});
