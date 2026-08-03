const CACHE_NAME = 'jungle-pizza-v1';
const ASSETS = [
  'index.php',
  'manifest.json',
  'public/css/login.css',
  'assets/img/logo-192.png',
  'assets/img/logo-512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});
