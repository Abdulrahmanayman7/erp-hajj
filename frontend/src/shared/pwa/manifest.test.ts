import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../../public')
const iconsDir = resolve(root, 'icons')

function pngDimensions(path: string): { width: number; height: number } {
  const buf = readFileSync(path)
  expect(buf.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))).toBe(true)
  return {
    width: buf.readUInt32BE(16),
    height: buf.readUInt32BE(20),
  }
}

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

  it('references the required global any and maskable icon paths', () => {
    const raw = readFileSync(resolve(root, 'manifest.webmanifest'), 'utf8')
    const manifest = JSON.parse(raw) as {
      icons: Array<{ src: string; purpose: string; sizes: string }>
    }

    const any192 = manifest.icons.find((i) => i.src === '/icons/icon-192.png')
    const any512 = manifest.icons.find((i) => i.src === '/icons/icon-512.png')
    const mask192 = manifest.icons.find((i) => i.src === '/icons/icon-192-maskable.png')
    const mask512 = manifest.icons.find((i) => i.src === '/icons/icon-512-maskable.png')

    expect(any192).toMatchObject({ sizes: '192x192', purpose: 'any' })
    expect(any512).toMatchObject({ sizes: '512x512', purpose: 'any' })
    expect(mask192).toMatchObject({ sizes: '192x192', purpose: 'maskable' })
    expect(mask512).toMatchObject({ sizes: '512x512', purpose: 'maskable' })
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
  })
})
