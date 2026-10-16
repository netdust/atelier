// One card offline: network first, the last good copy when there is none.
// CARD ({ prefix, cache, urls }) is prepended by CardService, which owns the list.
const paths = new Set(CARD.urls.map((url) => new URL(url, self.location).pathname));

// Not cache.add(): it follows a redirect and keeps the result, which a navigation then refuses offline.
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CARD.cache)
      .then((cache) =>
        Promise.all(
          CARD.urls.map((url) =>
            fetch(new Request(url, { cache: 'reload' }))
              .then((response) => response.ok && !response.redirected && cache.put(url, response))
              .catch(() => {}),
          ),
        ),
      )
      .then(() => self.skipWaiting()),
  );
});

// Only this card's older caches: another person's card on the same phone keeps its own.
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith(CARD.prefix) && key !== CARD.cache).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.origin !== self.location.origin || !paths.has(url.pathname)) return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response.ok && response.type === 'basic' && !response.redirected) {
          const copy = response.clone();
          event.waitUntil(caches.open(CARD.cache).then((cache) => cache.put(event.request, copy)));
        }
        return response;
      })
      .catch(() =>
        caches
          .match(event.request)
          .then((hit) => hit || caches.match(event.request, { ignoreSearch: true }))
          .then((hit) => hit || Response.error()),
      ),
  );
});
