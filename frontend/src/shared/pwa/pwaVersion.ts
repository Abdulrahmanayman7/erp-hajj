/**
 * Single source of truth for PWA metadata / icon cache-busting and shell cache naming.
 *
 * Bump `PWA_ASSET_VERSION` when install icons or manifest identity assets change,
 * then run `npm run pwa:sync` (also runs automatically before test/build).
 *
 * This does NOT force Android/iOS to replace an already-installed OS launcher icon;
 * see docs/08-deployment/PWA.md.
 */
export const PWA_ASSET_VERSION = '4'

/** Cache Storage name for hashed `/assets/*` shell entries. */
export const PWA_SHELL_CACHE = `erp-hajj-shell-v${PWA_ASSET_VERSION}`

export const PWA_MANIFEST_PATH = '/manifest.webmanifest'
export const PWA_SERVICE_WORKER_PATH = '/sw.js'

export const PWA_ICON_PATHS = {
  favicon32: '/icons/icon-32.png',
  any192: '/icons/icon-192.png',
  any512: '/icons/icon-512.png',
  maskable192: '/icons/icon-192-maskable.png',
  maskable512: '/icons/icon-512-maskable.png',
  appleTouch: '/icons/apple-touch-icon.png',
} as const

/** Append `?v=<PWA_ASSET_VERSION>` for HTTP cache busting of stable PWA asset paths. */
export function withPwaAssetVersion(path: string): string {
  const base = path.split('?')[0] ?? path
  return `${base}?v=${PWA_ASSET_VERSION}`
}

export const PWA_MANIFEST_HREF = withPwaAssetVersion(PWA_MANIFEST_PATH)

export const PWA_ICON_HREFS = {
  favicon32: withPwaAssetVersion(PWA_ICON_PATHS.favicon32),
  any192: withPwaAssetVersion(PWA_ICON_PATHS.any192),
  any512: withPwaAssetVersion(PWA_ICON_PATHS.any512),
  maskable192: withPwaAssetVersion(PWA_ICON_PATHS.maskable192),
  maskable512: withPwaAssetVersion(PWA_ICON_PATHS.maskable512),
  appleTouch: withPwaAssetVersion(PWA_ICON_PATHS.appleTouch),
} as const
