/**
 * ERP Hajj shell service worker (manual — not Workbox / vite-plugin-pwa).
 *
 * Policy:
 * - Navigations: network-first (always prefer fresh index.html after deploy).
 * - Hashed /assets/*: cache-first (safe; filenames change per build).
 * - Never cache /api/ or authenticated JSON.
 * - Bump CACHE_VERSION on intentional shell-cache strategy changes.
 */
const CACHE_VERSION = 'erp-hajj-shell-v3'
const SHELL_CACHE = CACHE_VERSION

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting())
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys()
      await Promise.all(
        keys
          .filter((key) => key.startsWith('erp-hajj-shell-') && key !== SHELL_CACHE)
          .map((key) => caches.delete(key)),
      )
      await self.clients.claim()
    })(),
  )
})

/**
 * @param {Request} request
 * @returns {boolean}
 */
function isApiRequest(request) {
  try {
    const url = new URL(request.url)
    return url.pathname.startsWith('/api/')
  } catch {
    return false
  }
}

/**
 * @param {Request} request
 * @returns {boolean}
 */
function isHashedAsset(request) {
  try {
    const url = new URL(request.url)
    return url.origin === self.location.origin && url.pathname.startsWith('/assets/')
  } catch {
    return false
  }
}

self.addEventListener('fetch', (event) => {
  const { request } = event

  if (request.method !== 'GET') {
    return
  }

  // Never intercept API — ERP mutations/auth must stay network-driven.
  if (isApiRequest(request)) {
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstNavigation(request))
    return
  }

  if (isHashedAsset(request)) {
    event.respondWith(cacheFirstAsset(request))
  }
})

/**
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function networkFirstNavigation(request) {
  try {
    const fresh = await fetch(request)
    return fresh
  } catch {
    const cached = await caches.match('/index.html')
    if (cached) {
      return cached
    }
    return new Response('Offline', {
      status: 503,
      statusText: 'Service Unavailable',
      headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    })
  }
}

/**
 * @param {Request} request
 * @returns {Promise<Response>}
 */
async function cacheFirstAsset(request) {
  const cache = await caches.open(SHELL_CACHE)
  const cached = await cache.match(request)
  if (cached) {
    return cached
  }

  const response = await fetch(request)
  if (response.ok) {
    await cache.put(request, response.clone())
  }
  return response
}
