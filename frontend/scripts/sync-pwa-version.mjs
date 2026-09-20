/**
 * Sync PWA versioned URLs into static public assets from pwaVersion.ts.
 *
 * Usage: node scripts/sync-pwa-version.mjs
 * Invoked automatically via npm prebuild / pretest.
 */
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const versionFile = resolve(root, 'src/shared/pwa/pwaVersion.ts')
const source = readFileSync(versionFile, 'utf8')

const match = source.match(/export const PWA_ASSET_VERSION = '([^']+)'/)
if (!match) {
  console.error('Unable to parse PWA_ASSET_VERSION from pwaVersion.ts')
  process.exit(1)
}

const version = match[1]
const shellCache = `erp-hajj-shell-v${version}`
const v = (path) => `${path}?v=${version}`

// --- manifest.webmanifest ---
const manifestPath = resolve(root, 'public/manifest.webmanifest')
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
manifest.icons = [
  {
    src: v('/icons/icon-192.png'),
    sizes: '192x192',
    type: 'image/png',
    purpose: 'any',
  },
  {
    src: v('/icons/icon-512.png'),
    sizes: '512x512',
    type: 'image/png',
    purpose: 'any',
  },
  {
    src: v('/icons/icon-192-maskable.png'),
    sizes: '192x192',
    type: 'image/png',
    purpose: 'maskable',
  },
  {
    src: v('/icons/icon-512-maskable.png'),
    sizes: '512x512',
    type: 'image/png',
    purpose: 'maskable',
  },
]
writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, 'utf8')

// --- index.html ---
const indexPath = resolve(root, 'index.html')
let indexHtml = readFileSync(indexPath, 'utf8')
indexHtml = indexHtml.replace(
  /<link rel="manifest" href="[^"]+" \/>/,
  `<link rel="manifest" href="${v('/manifest.webmanifest')}" />`,
)
indexHtml = indexHtml.replace(
  /<link rel="icon" type="image\/png" sizes="32x32" href="[^"]+" \/>/,
  `<link rel="icon" type="image/png" sizes="32x32" href="${v('/icons/icon-32.png')}" />`,
)
indexHtml = indexHtml.replace(
  /<link rel="icon" type="image\/png" sizes="192x192" href="[^"]+" \/>/,
  `<link rel="icon" type="image/png" sizes="192x192" href="${v('/icons/icon-192.png')}" />`,
)
indexHtml = indexHtml.replace(
  /<link rel="apple-touch-icon" sizes="180x180" href="[^"]+" \/>/,
  `<link rel="apple-touch-icon" sizes="180x180" href="${v('/icons/apple-touch-icon.png')}" />`,
)
writeFileSync(indexPath, indexHtml, 'utf8')

// --- sw.js ---
const swPath = resolve(root, 'public/sw.js')
let sw = readFileSync(swPath, 'utf8')
if (!/const CACHE_VERSION = 'erp-hajj-shell-v[^']+'/.test(sw)) {
  console.error('Unable to locate CACHE_VERSION in public/sw.js')
  process.exit(1)
}
sw = sw.replace(
  /const CACHE_VERSION = 'erp-hajj-shell-v[^']+'/,
  `const CACHE_VERSION = '${shellCache}'`,
)
writeFileSync(swPath, sw, 'utf8')

console.log(`PWA sync complete: asset v=${version}, shell cache=${shellCache}`)
