import { afterEach, describe, expect, it, vi } from 'vitest'

import {
  applyStandaloneClass,
  getDisplayMode,
  isStandaloneDisplay,
} from './displayMode'

describe('displayMode', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    document.documentElement.classList.remove('is-standalone')
  })

  it('detects standalone via matchMedia display-mode', () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('display-mode: standalone'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    expect(isStandaloneDisplay()).toBe(true)
    expect(getDisplayMode()).toBe('standalone')
  })

  it('detects iOS navigator.standalone', () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )
    vi.stubGlobal('navigator', {
      ...navigator,
      standalone: true,
    })

    expect(isStandaloneDisplay()).toBe(true)
  })

  it('returns browser mode when not installed', () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )
    vi.stubGlobal('navigator', {
      ...navigator,
      standalone: false,
    })

    expect(isStandaloneDisplay()).toBe(false)
    expect(getDisplayMode()).toBe('browser')
  })

  it('toggles is-standalone class on documentElement', () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('display-mode: standalone'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    expect(applyStandaloneClass()).toBe(true)
    expect(document.documentElement.classList.contains('is-standalone')).toBe(true)
  })
})
