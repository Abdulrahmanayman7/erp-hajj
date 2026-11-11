/**
 * Mock Browser UAT network policy — decide before any request is continued.
 *
 * Decisions: 'mock-api' | 'allow-fe' | 'deny'
 * Same-origin /api and /sanctum are never allowed through to the network.
 * Unknown hosts (including external /api URLs) are denied — never mocked as success.
 */

/**
 * @param {string} requestUrl
 * @param {{ feOrigin: string }} opts
 * @returns {'mock-api' | 'allow-fe' | 'deny'}
 */
export function classifyRequest(requestUrl, opts) {
  let url
  try {
    url = new URL(requestUrl)
  } catch {
    return 'deny'
  }

  const fe = new URL(opts.feOrigin)
  const sameOrigin = url.origin === fe.origin
  const isLocalLaravel =
    (url.hostname === 'localhost' || url.hostname === '127.0.0.1') && url.port === '8000'

  // 1) API / Sanctum on FE origin or local Laravel — mock (never continue to network).
  if (isApiOrSanctumPath(url.pathname) && (sameOrigin || isLocalLaravel)) {
    return 'mock-api'
  }

  // 2) Any other API/sanctum (external / unexpected host) — deny hard.
  if (isApiOrSanctumPath(url.pathname)) {
    return 'deny'
  }

  // 3) Explicitly allowed frontend origin assets / Vite modules / SPA routes.
  if (sameOrigin) {
    return 'allow-fe'
  }

  // 4) Known public font CDNs used by index.html (read-only).
  if (url.hostname.includes('googleapis.com') || url.hostname.includes('gstatic.com')) {
    return 'allow-fe'
  }

  // 5) Default deny — including unexpected external hosts and redirect targets.
  return 'deny'
}

export function isApiOrSanctumPath(pathname) {
  return pathname.startsWith('/api/') || pathname.startsWith('/sanctum/')
}
