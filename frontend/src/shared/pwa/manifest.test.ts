import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../../../public')

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
      icons: Array<{ src: string; purpose: string; sizes: string }>
    }

    expect(manifest.lang).toBe('ar')
    expect(manifest.dir).toBe('rtl')
    expect(manifest.display).toBe('standalone')
    expect(manifest.start_url).toBe('/')
    expect(manifest.scope).toBe('/')
    expect(manifest.short_name).toBe('رفيع')
    expect(manifest.name).toContain('ERP Hajj')

    const purposes = manifest.icons.map((icon) => icon.purpose).sort()
    expect(purposes).toEqual(['any', 'any', 'maskable', 'maskable'])
    expect(manifest.icons.some((i) => i.sizes === '192x192' && i.purpose === 'any')).toBe(true)
    expect(manifest.icons.some((i) => i.sizes === '512x512' && i.purpose === 'any')).toBe(true)
    expect(manifest.icons.every((i) => i.src.startsWith('/icons/'))).toBe(true)
  })
})
