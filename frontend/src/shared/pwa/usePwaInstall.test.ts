import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, defineComponent, nextTick } from 'vue'

import { usePwaInstall } from './usePwaInstall'

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

describe('usePwaInstall', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    localStorage.clear()
  })

  it('captures beforeinstallprompt and exposes canInstall', async () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    const { result, unmount } = await withComposable(() => usePwaInstall())

    expect(result.canInstall.value).toBe(false)

    const prompt = vi.fn().mockResolvedValue(undefined)
    const userChoice = Promise.resolve({ outcome: 'accepted' as const, platform: 'web' })
    const event = new Event('beforeinstallprompt')
    Object.defineProperty(event, 'prompt', { value: prompt })
    Object.defineProperty(event, 'userChoice', { value: userChoice })

    window.dispatchEvent(event)
    await nextTick()

    expect(result.canInstall.value).toBe(true)
    expect(result.platform.value).toBe('chromium')

    const outcome = await result.promptInstall()
    expect(outcome).toBe('accepted')
    expect(prompt).toHaveBeenCalledOnce()
    expect(result.isInstalled.value).toBe(true)

    unmount()
  })

  it('hides install after appinstalled', async () => {
    vi.stubGlobal(
      'matchMedia',
      vi.fn().mockImplementation((query: string) => ({
        matches: false,
        media: query,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      })),
    )

    const { result, unmount } = await withComposable(() => usePwaInstall())
    window.dispatchEvent(new Event('appinstalled'))
    await nextTick()

    expect(result.isInstalled.value).toBe(true)
    expect(result.canInstall.value).toBe(false)
    unmount()
  })
})
