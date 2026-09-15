import { beforeEach, describe, expect, it, vi } from 'vitest'

const invalidateQueries = vi.fn(async () => undefined)
const back = vi.fn()
const push = vi.fn(async () => undefined)

vi.mock('@tanstack/vue-query', () => ({
  useQueryClient: () => ({ invalidateQueries }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ back, push }),
}))

describe('useAppChromeNavigation', () => {
  beforeEach(() => {
    invalidateQueries.mockClear()
    back.mockClear()
    push.mockClear()
    vi.resetModules()
    vi.useRealTimers()
  })

  it('goes back when history exists, otherwise navigates to /app', async () => {
    const { useAppChromeNavigation } = await import('./useAppChromeNavigation')
    const nav = useAppChromeNavigation()

    Object.defineProperty(window, 'history', {
      configurable: true,
      value: { length: 3 },
    })
    nav.goBack()
    expect(back).toHaveBeenCalledTimes(1)

    Object.defineProperty(window, 'history', {
      configurable: true,
      value: { length: 1 },
    })
    nav.goBack()
    expect(push).toHaveBeenCalledWith('/app')
  })

  it('soft-refreshes on short press and hard-refreshes after 5s hold', async () => {
    vi.useFakeTimers()
    const reload = vi.fn()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { reload },
    })

    const { useAppChromeNavigation } = await import('./useAppChromeNavigation')
    const nav = useAppChromeNavigation()

    nav.onRefreshPointerDown(new PointerEvent('pointerdown', { button: 0 }))
    nav.onRefreshPointerUp()
    expect(invalidateQueries).toHaveBeenCalledTimes(1)
    expect(reload).not.toHaveBeenCalled()

    nav.onRefreshPointerDown(new PointerEvent('pointerdown', { button: 0 }))
    await vi.advanceTimersByTimeAsync(5000)
    expect(reload).toHaveBeenCalledTimes(1)
  })
})
