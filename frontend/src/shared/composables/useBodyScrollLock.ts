import { onUnmounted, watch, type Ref, type ComputedRef } from 'vue'

let lockCount = 0
let savedScrollY = 0
let savedBodyOverflow = ''
let savedBodyPosition = ''
let savedBodyTop = ''
let savedBodyWidth = ''
let savedHtmlOverflow = ''

function applyLock(): void {
  if (typeof document === 'undefined') return
  if (lockCount === 0) {
    savedScrollY = window.scrollY || window.pageYOffset || 0
    savedBodyOverflow = document.body.style.overflow
    savedBodyPosition = document.body.style.position
    savedBodyTop = document.body.style.top
    savedBodyWidth = document.body.style.width
    savedHtmlOverflow = document.documentElement.style.overflow

    document.documentElement.style.overflow = 'hidden'
    document.body.style.overflow = 'hidden'
    document.body.style.position = 'fixed'
    document.body.style.top = `-${savedScrollY}px`
    document.body.style.width = '100%'
  }
  lockCount += 1
}

function releaseLock(): void {
  if (typeof document === 'undefined') return
  if (lockCount === 0) return
  lockCount -= 1
  if (lockCount > 0) return

  document.documentElement.style.overflow = savedHtmlOverflow
  document.body.style.overflow = savedBodyOverflow
  document.body.style.position = savedBodyPosition
  document.body.style.top = savedBodyTop
  document.body.style.width = savedBodyWidth

  window.scrollTo(0, savedScrollY)
}

/**
 * Locks document scroll while `open` is true.
 * Supports nested locks (multiple sheets) without jump on close.
 */
export function useBodyScrollLock(open: Ref<boolean> | ComputedRef<boolean>): void {
  watch(
    open,
    (isOpen, wasOpen) => {
      if (isOpen && !wasOpen) {
        applyLock()
        return
      }
      if (!isOpen && wasOpen) {
        releaseLock()
      }
    },
    { flush: 'sync' },
  )

  onUnmounted(() => {
    if (open.value) {
      releaseLock()
    }
  })
}

/** Test helper — reset lock counter between unit tests. */
export function __resetBodyScrollLockForTests(): void {
  lockCount = 0
  savedScrollY = 0
  savedBodyOverflow = ''
  savedBodyPosition = ''
  savedBodyTop = ''
  savedBodyWidth = ''
  savedHtmlOverflow = ''
  if (typeof document !== 'undefined') {
    document.documentElement.style.overflow = ''
    document.body.style.overflow = ''
    document.body.style.position = ''
    document.body.style.top = ''
    document.body.style.width = ''
  }
}
