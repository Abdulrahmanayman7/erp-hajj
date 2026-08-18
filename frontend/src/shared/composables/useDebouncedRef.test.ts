import { describe, expect, it, vi } from 'vitest'
import { effectScope, ref } from 'vue'

import { useDebouncedRef } from './useDebouncedRef'

describe('useDebouncedRef', () => {
  it('keeps the initial source value immediately', () => {
    const scope = effectScope()
    scope.run(() => {
      const source = ref('alpha')
      const delayed = useDebouncedRef(source, 300)
      expect(delayed.value).toBe('alpha')
    })
    scope.stop()
  })

  it('commits the latest value once after the delay', () => {
    vi.useFakeTimers()
    const scope = effectScope()
    scope.run(() => {
      const source = ref('')
      const delayed = useDebouncedRef(source, 300)

      source.value = 'a'
      source.value = 'ab'
      source.value = 'abc'
      expect(delayed.value).toBe('')

      vi.advanceTimersByTime(299)
      expect(delayed.value).toBe('')

      vi.advanceTimersByTime(1)
      expect(delayed.value).toBe('abc')
    })
    scope.stop()
    vi.useRealTimers()
  })
})
