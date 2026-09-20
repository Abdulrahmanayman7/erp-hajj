import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

import {
  PWA_ASSET_VERSION,
  PWA_ICON_HREFS,
  PWA_ICON_PATHS,
  PWA_MANIFEST_HREF,
  PWA_SHELL_CACHE,
  withPwaAssetVersion,
} from './pwaVersion'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../../public')
const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..')
const iconsDir = resolve(root, 'icons')

function pngDimensions(path: string): { width: number; height: number } {
  const buf = readFileSync(path)
  expect(buf.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))).toBe(true)
  return {
    width: buf.readUInt32BE(16),
    height: buf.readUInt32BE(20),
  }
}

describe('PWA version strategy', () => {
  it('exposes a single asset version used for cache busting and shell cache naming', () => {
    expect(PWA_ASSET_VERSION).toBe('4')
    expect(PWA_SHELL_CACHE).toBe(`erp-hajj-shell-v${PWA_ASSET_VERSION}`)
    expect(withPwaAssetVersion('/icons/icon-192.png')).toBe(`/icons/icon-192.png?v=${PWA_ASSET_VERSION}`)
    expect(PWA_MANIFEST_HREF).toBe(`/manifest.webmanifest?v=${PWA_ASSET_VERSION}`)
  })
})

describe('PWA manifest', () => {
  it('uses global branding and standalone display', () => {
    const raw = readFileSync(resolve(root, 'manifest.webmanifest'), 'utf8')
    const manifest = JSON.parse(raw) as {
      name: string
      short_name: string
      lang: string
      dir: string
      display: string
      start_url: string
      scope: string
      theme_color: string
      background_color: string
      icons: Array<{ src: string; purpose: string; sizes: string; type: string }>
    }

    expect(manifest.lang).toBe('ar')
    expect(manifest.dir).toBe('rtl')
    expect(manifest.display).toBe('standalone')
    expect(manifest.start_url).toBe('/')
    expect(manifest.scope).toBe('/')
    expect(manifest.short_name).toBe('رفيع')
    expect(manifest.name).toContain('ERP Hajj')
    expect(manifest.theme_color).toBe('#ffffff')
    expect(manifest.background_color).toBe('#064e3b')

    const purposes = manifest.icons.map((icon) => icon.purpose).sort()
    expect(purposes).toEqual(['any', 'any', 'maskable', 'maskable'])
    expect(manifest.icons.every((i) => i.src.startsWith('/icons/'))).toBe(true)
  })

  it('references versioned global any and maskable icon paths', () => {
    const raw = readFileSync(resolve(root, 'manifest.webmanifest'), 'utf8')
    const manifest = JSON.parse(raw) as {
      icons: Array<{ src: string; purpose: string; sizes: string }>
    }

    const any192 = manifest.icons.find((i) => i.src === PWA_ICON_HREFS.any192)
    const any512 = manifest.icons.find((i) => i.src === PWA_ICON_HREFS.any512)
    const mask192 = manifest.icons.find((i) => i.src === PWA_ICON_HREFS.maskable192)
    const mask512 = manifest.icons.find((i) => i.src === PWA_ICON_HREFS.maskable512)

    expect(any192).toMatchObject({ sizes: '192x192', purpose: 'any' })
    expect(any512).toMatchObject({ sizes: '512x512', purpose: 'any' })
    expect(mask192).toMatchObject({ sizes: '192x192', purpose: 'maskable' })
    expect(mask512).toMatchObject({ sizes: '512x512', purpose: 'maskable' })

    expect(manifest.icons.every((i) => i.src.includes(`?v=${PWA_ASSET_VERSION}`))).toBe(true)
  })
})

describe('PWA HTML links', () => {
  it('points manifest, favicon, and apple-touch-icon at the current asset version', () => {
    const html = readFileSync(resolve(frontendRoot, 'index.html'), 'utf8')

    expect(html).toContain(`href="${PWA_MANIFEST_HREF}"`)
    expect(html).toContain(`href="${PWA_ICON_HREFS.favicon32}"`)
    expect(html).toContain(`href="${PWA_ICON_HREFS.any192}"`)
    expect(html).toContain(`href="${PWA_ICON_HREFS.appleTouch}"`)
  })
})

describe('PWA service worker', () => {
  it('uses the current shell cache version and never caches API or PWA identity assets', () => {
    const sw = readFileSync(resolve(root, 'sw.js'), 'utf8')

    expect(sw).toContain(`const CACHE_VERSION = '${PWA_SHELL_CACHE}'`)
    expect(sw).toContain("url.pathname.startsWith('/api/')")
    expect(sw).toContain("path === '/manifest.webmanifest'")
    expect(sw).toContain("path === '/sw.js'")
    expect(sw).toContain("path.startsWith('/icons/')")
    expect(sw).toContain('isPwaMetadataRequest')
    expect(sw).toContain("type === 'SKIP_WAITING'")
  })
})

describe('PWA icon assets', () => {
  it('ships real PNG icons at the required dimensions', () => {
    const expected: Array<{ file: string; width: number; height: number }> = [
      { file: 'icon-192.png', width: 192, height: 192 },
      { file: 'icon-512.png', width: 512, height: 512 },
      { file: 'icon-192-maskable.png', width: 192, height: 192 },
      { file: 'icon-512-maskable.png', width: 512, height: 512 },
      { file: 'apple-touch-icon.png', width: 180, height: 180 },
      { file: 'icon-32.png', width: 32, height: 32 },
    ]

    for (const item of expected) {
      const path = resolve(iconsDir, item.file)
      expect(existsSync(path), item.file).toBe(true)
      const dims = pngDimensions(path)
      expect(dims).toEqual({ width: item.width, height: item.height })
    }

    // Stable filenames (versioning is via ?v= query, not renamed files).
    expect(PWA_ICON_PATHS.any192).toBe('/icons/icon-192.png')
    expect(PWA_ICON_PATHS.maskable512).toBe('/icons/icon-512-maskable.png')
  })
})
