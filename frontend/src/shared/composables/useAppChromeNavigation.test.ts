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

    nav.onRefreshPointerDown(new PointerEvent('pointerdown', { button: 0, cancelable: true }))
    nav.onRefreshPointerUp()
    expect(invalidateQueries).toHaveBeenCalledTimes(1)
    expect(reload).not.toHaveBeenCalled()

    nav.onRefreshPointerDown(new PointerEvent('pointerdown', { button: 0, cancelable: true }))
    await vi.advanceTimersByTimeAsync(5000)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('keeps a touch long-press alive after pointercancel so 5s still hard-refreshes', async () => {
    vi.useFakeTimers()
    const reload = vi.fn()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { reload },
    })

    const { useAppChromeNavigation } = await import('./useAppChromeNavigation')
    const nav = useAppChromeNavigation()

    nav.onRefreshPointerDown(
      new PointerEvent('pointerdown', { button: 0, cancelable: true, pointerType: 'touch' }),
    )
    nav.onRefreshPointerCancel(
      new PointerEvent('pointercancel', { pointerType: 'touch', cancelable: true }),
    )
    expect(reload).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(5000)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('cancels a mouse hold on pointerleave without refreshing', async () => {
    vi.useFakeTimers()
    const reload = vi.fn()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { reload },
    })

    const { useAppChromeNavigation } = await import('./useAppChromeNavigation')
    const nav = useAppChromeNavigation()

    nav.onRefreshPointerDown(
      new PointerEvent('pointerdown', { button: 0, cancelable: true, pointerType: 'mouse' }),
    )
    nav.onRefreshPointerLeave(
      new PointerEvent('pointerleave', { pointerType: 'mouse', cancelable: true }),
    )
    expect(invalidateQueries).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(5000)
    expect(reload).not.toHaveBeenCalled()
  })
})
