import { getCurrentInstance, onUnmounted, ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue'

export const DEFAULT_SEARCH_DEBOUNCE_MS = 300

/**
 * Returns a ref that lags the source by `delayMs`.
 * List pages use this so search keystrokes do not hit the API until typing pauses.
 */
export function useDebouncedRef<T>(
  source: MaybeRefOrGetter<T>,
  delayMs = DEFAULT_SEARCH_DEBOUNCE_MS,
): Ref<T> {
  const value = ref(toValue(source)) as Ref<T>
  let timer: ReturnType<typeof setTimeout> | undefined

  watch(
    () => toValue(source),
    (next) => {
      if (timer !== undefined) {
        clearTimeout(timer)
      }
      timer = setTimeout(() => {
        value.value = next
        timer = undefined
      }, delayMs)
    },
    { flush: 'sync' },
  )

  if (getCurrentInstance()) {
    onUnmounted(() => {
      if (timer !== undefined) {
        clearTimeout(timer)
      }
    })
  }

  return value
}
