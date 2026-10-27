import { sessionGeneration } from '@/shared/api/sessionLifetime'
import { readonly, ref } from 'vue'

export type ToastVariant = 'success' | 'error' | 'info'

export interface ToastItem {
  id: number
  message: string
  variant: ToastVariant
}

const toasts = ref<ToastItem[]>([])
let seed = 0
const timers = new Map<number, ReturnType<typeof setTimeout>>()

function dismiss(id: number): void {
  const timer = timers.get(id)
  if (timer != null) {
    clearTimeout(timer)
    timers.delete(id)
  }
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
}

function push(message: string, variant: ToastVariant = 'success', durationMs = 4200): void {
  const id = ++seed
  toasts.value = [...toasts.value, { id, message, variant }]
  timers.set(
    id,
    setTimeout(() => {
      dismiss(id)
    }, durationMs),
  )
}

export function useToast() {
  const generation = sessionGeneration.value
  const scopedPush = (message: string, variant: ToastVariant, durationMs?: number) => {
    if (generation === sessionGeneration.value) push(message, variant, durationMs)
  }
  return {
    toasts: readonly(toasts),
    success: (message: string, durationMs?: number) => scopedPush(message, 'success', durationMs),
    error: (message: string, durationMs?: number) => scopedPush(message, 'error', durationMs),
    info: (message: string, durationMs?: number) => scopedPush(message, 'info', durationMs),
    dismiss,
  }
}

export function clearToasts(): void {
  for (const id of timers.keys()) dismiss(id)
  toasts.value = []
}
