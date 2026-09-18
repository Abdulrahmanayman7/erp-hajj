import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, nextTick } from 'vue'

import { useBreakpoint } from './useBreakpoint'

async function withComposable<T>(factory: () => T): Promise<{ result: T; unmount: () => void }> {
  let result!: T
  const Host = defineComponent({
    setup() {
      result = factory()
      return () => null
    },
  })
  const el = document.createElement('div')
  document.body.appendChild(el)
  const app = createApp(Host)
  app.mount(el)
  await nextTick()
  return {
    result,
    unmount: () => {
      app.unmount()
      el.remove()
    },
  }
}

describe('useBreakpoint', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('classifies mobile / tablet / desktop by width', async () => {
    vi.stubGlobal('innerWidth', 390)
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('max-width: 767') ? true : false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    const { result, unmount } = await withComposable(() => useBreakpoint())
    expect(result.isMobile.value).toBe(true)
    expect(result.showBottomNav.value).toBe(true)
    expect(result.showFullSidebar.value).toBe(false)
    unmount()
  })

  it('shows nav rail on tablet widths', async () => {
    vi.stubGlobal('innerWidth', 900)
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    const { result, unmount } = await withComposable(() => useBreakpoint())
    expect(result.isTablet.value).toBe(true)
    expect(result.showNavRail.value).toBe(true)
    expect(result.showFullSidebar.value).toBe(false)
    unmount()
  })

  it('shows full sidebar on desktop', async () => {
    vi.stubGlobal('innerWidth', 1440)
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: query.includes('min-width: 1280'),
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    const { result, unmount } = await withComposable(() => useBreakpoint())
    expect(result.isDesktop.value).toBe(true)
    expect(result.showFullSidebar.value).toBe(true)
    expect(result.showNavRail.value).toBe(false)
    unmount()
  })
})
