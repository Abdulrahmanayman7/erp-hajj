import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import { ref, nextTick } from 'vue'

import {
  __resetBodyScrollLockForTests,
  useBodyScrollLock,
} from './useBodyScrollLock'

describe('useBodyScrollLock', () => {
  beforeEach(() => {
    __resetBodyScrollLockForTests()
    window.scrollTo = vi.fn() as unknown as typeof window.scrollTo
    Object.defineProperty(window, 'scrollY', { value: 120, writable: true, configurable: true })
  })

  afterEach(() => {
    __resetBodyScrollLockForTests()
  })

  it('locks body scroll when open becomes true', async () => {
    const open = ref(false)
    useBodyScrollLock(open)
    open.value = true
    await nextTick()

    expect(document.body.style.overflow).toBe('hidden')
    expect(document.body.style.position).toBe('fixed')
    expect(document.body.style.top).toBe('-120px')
  })

  it('restores scroll when closed', async () => {
    const open = ref(false)
    useBodyScrollLock(open)
    open.value = true
    await nextTick()
    open.value = false
    await nextTick()

    expect(document.body.style.position).toBe('')
    expect(window.scrollTo).toHaveBeenCalledWith(0, 120)
  })

  it('supports nested locks', async () => {
    const a = ref(false)
    const b = ref(false)
    useBodyScrollLock(a)
    useBodyScrollLock(b)

    a.value = true
    await nextTick()
    b.value = true
    await nextTick()
    b.value = false
    await nextTick()

    expect(document.body.style.position).toBe('fixed')

    a.value = false
    await nextTick()
    expect(document.body.style.position).toBe('')
  })
})
