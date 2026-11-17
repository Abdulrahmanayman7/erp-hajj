import { computed, ref, toValue, watch, type ComputedRef, type MaybeRefOrGetter } from 'vue'

function stableSerialize(value: unknown): string {
  if (value === null || value === undefined) return String(value)
  if (typeof value !== 'object') return JSON.stringify(value)
  if (value instanceof File) {
    return `File:${value.name}:${value.size}:${value.lastModified}`
  }
  if (Array.isArray(value)) {
    return `[${value.map((item) => stableSerialize(item)).join(',')}]`
  }
  const record = value as Record<string, unknown>
  const keys = Object.keys(record).sort()
  return `{${keys.map((key) => `${JSON.stringify(key)}:${stableSerialize(record[key])}`).join(',')}}`
}

/**
 * Snapshot-based dirty tracking for create/edit drawers.
 * Call `capture()` whenever the form is reset to a known clean baseline
 * (drawer open, after successful load, after discard).
 */
export function useFormDirty<T>(
  source: MaybeRefOrGetter<T>,
  options?: {
    enabled?: MaybeRefOrGetter<boolean>
  },
): {
  dirty: ComputedRef<boolean>
  capture: () => void
  reset: () => void
} {
  const baseline = ref('')
  const enabled = computed(() => (options?.enabled == null ? true : Boolean(toValue(options.enabled))))

  function capture(): void {
    baseline.value = stableSerialize(toValue(source))
  }

  function reset(): void {
    baseline.value = ''
  }

  const dirty = computed(() => {
    if (!enabled.value) return false
    if (!baseline.value) return false
    return stableSerialize(toValue(source)) !== baseline.value
  })

  return { dirty, capture, reset }
}

/**
 * Capture a clean baseline when `open` becomes true (after optional sync tick).
 */
export function useCaptureOnOpen(
  open: MaybeRefOrGetter<boolean>,
  capture: () => void,
  options?: { resetOnClose?: boolean; onClose?: () => void },
): void {
  watch(
    () => Boolean(toValue(open)),
    (isOpen) => {
      if (isOpen) {
        // Defer so callers can hydrate reactive form fields in the same tick.
        queueMicrotask(() => capture())
        return
      }
      options?.onClose?.()
      if (options?.resetOnClose !== false) {
        // baseline cleared by caller via capture's companion if needed
      }
    },
  )
}
