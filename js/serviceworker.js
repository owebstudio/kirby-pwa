/* global config */

// `config` is added by the `serviceworker.js` route

const absolute = (url) => {
  return new URL(url, self.location.href).href;
};

const PREFIX = 'kirby-pwa-';
const PRECACHE = PREFIX + config.version + '-precache';
const PAGES = PREFIX + config.version + '-pages';
const ASSETS = PREFIX + config.version + '-assets';

const exclude = config.exclude.map((source) => new RegExp(source));
const strategies = config.strategies.map(([source, strategy]) => [new RegExp(source), strategy]);
const media = new RegExp(config.media);
const panel = new RegExp(config.panel);

const log = (...args) => {
  if (config.debug) {
    console.log('Kirby PWA:', ...args);
  }
};

const isCacheable = (response) => {
  return response.ok === true && response.type === 'basic';
};

const isPrivate = (response) => {
  return /no-store/i.test(response.headers.get('Cache-Control') || '');
};

// Cache the offline page, the start URL and the `precache` option
const precache = async () => {
  const cache = await caches.open(PRECACHE);
  const urls = new Set([
    config.offline,
    new URL(config.start, absolute(config.manifest)).href,
    ...config.precache
  ].filter(Boolean).map(absolute));

  await Promise.all([...urls].map(async (url) => {
    if (new URL(url).origin !== self.location.origin) {
      return;
    }

    try {
      let response = await fetch(url, { cache: 'reload', credentials: 'same-origin' });

      if (isCacheable(response) === false) {
        throw new Error('HTTP ' + response.status);
      }

      // a redirected response can't answer a navigation
      if (response.redirected === true) {
        response = new Response(await response.blob(), {
          status: response.status,
          statusText: response.statusText,
          headers: response.headers
        });
      }

      await cache.put(url, response);
    } catch (error) {
      log('Could not cache ' + url, error);
    }
  }));
};

// Remove old entries when a cache is over its limit
const trim = async (cacheName) => {
  const limit = cacheName === PAGES ? config.limits.pages : config.limits.assets;
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();

  await Promise.all(
    keys.slice(0, Math.max(keys.length - limit, 0)).map((key) => cache.delete(key))
  );
};

const store = (event, cacheName, response) => {
  if (isCacheable(response) === false || isPrivate(response) === true) {
    return;
  }

  const copy = response.clone();

  event.waitUntil(
    caches.open(cacheName)
      .then((cache) => cache.put(event.request, copy))
      .then(() => trim(cacheName))
  );
};

const match = (request) => {
  return caches.match(request, { ignoreVary: true });
};

// Fetches and caches the response,
// a network failure becomes an error response
const fromNetwork = async (event, cacheName) => {
  try {
    const response = await fetch(event.request);
    store(event, cacheName, response);
    return response;
  } catch (error) {
    return Response.error();
  }
};

const networkFirst = async (event, cacheName) => {
  const response = await fromNetwork(event, cacheName);

  if (response.type !== 'error') {
    return response;
  }

  const cached = await match(event.request);

  if (cached) {
    return cached;
  }

  if (event.request.mode === 'navigate' && config.offline) {
    const offline = await match(config.offline);

    if (offline) {
      return offline;
    }
  }

  return response;
};

const cacheFirst = async (event, cacheName) => {
  const cached = await match(event.request);

  return cached || fromNetwork(event, cacheName);
};

const staleWhileRevalidate = async (event, cacheName) => {
  const cached = await match(event.request);
  const network = fromNetwork(event, cacheName);

  if (cached) {
    event.waitUntil(network);
    return cached;
  }

  return network;
};

// Returns the caching strategy for a request,
// or `null` for requests that go to the network untouched
const strategyFor = (request) => {
  if (request.method !== 'GET' || request.headers.has('range') === true) {
    return null;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return null;
  }

  // previews of drafts and unsaved changes
  if (url.searchParams.has('_token') || url.searchParams.has('_version')) {
    return null;
  }

  // Panel, API, the manifest, the service worker and the `exclude` option
  if (exclude.some((regex) => regex.test(url.pathname))) {
    return null;
  }

  // requests from Panel views
  if (request.referrer && panel.test(new URL(request.referrer).pathname)) {
    return null;
  }

  for (const [regex, strategy] of strategies) {
    if (regex.test(url.pathname)) {
      return strategy === 'network-only' ? null : strategy;
    }
  }

  if (request.mode === 'navigate') {
    return 'network-first';
  }

  // Kirby's media URLs change with every file change
  if (media.test(url.pathname)) {
    return 'cache-first';
  }

  if (['font', 'image', 'script', 'style'].includes(request.destination)) {
    return 'stale-while-revalidate';
  }

  return null;
};

self.addEventListener('install', (event) => {
  event.waitUntil(precache().then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const current = [PRECACHE, PAGES, ASSETS];
    const keys = await caches.keys();

    // caches of older versions, including the `pwa-v…` caches of 1.x
    await Promise.all(keys
      .filter((key) => key.startsWith(PREFIX) || key.startsWith('pwa-v'))
      .filter((key) => current.includes(key) === false)
      .map((key) => caches.delete(key))
    );

    await self.clients.claim();
    log('Version ' + config.version + ' is active');
  })());
});

self.addEventListener('fetch', (event) => {
  const strategy = strategyFor(event.request);

  if (strategy === null) {
    return;
  }

  if (strategy === 'cache-first') {
    event.respondWith(cacheFirst(event, ASSETS));
  } else if (strategy === 'stale-while-revalidate') {
    event.respondWith(staleWhileRevalidate(event, ASSETS));
  } else {
    event.respondWith(networkFirst(event, event.request.mode === 'navigate' ? PAGES : ASSETS));
  }
});
