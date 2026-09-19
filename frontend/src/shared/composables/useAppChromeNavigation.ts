import { onUnmounted, readonly, ref } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

const HOLD_MS = 5000

export function useAppChromeNavigation() {
  const router = useRouter()
  const queryClient = useQueryClient()

  const hardRefreshProgress = ref(0)
  const isHoldingRefresh = ref(false)

  let holdTimer: ReturnType<typeof setInterval> | null = null
  let holdStartedAt = 0
  let releaseBound = false

  function unbindReleaseListeners(): void {
    if (!releaseBound || typeof window === 'undefined') {
      return
    }
    window.removeEventListener('pointerup', finishHold, true)
    window.removeEventListener('touchend', finishHold, true)
    releaseBound = false
  }

  function bindReleaseListeners(): void {
    if (releaseBound || typeof window === 'undefined') {
      return
    }
    window.addEventListener('pointerup', finishHold, true)
    window.addEventListener('touchend', finishHold, true)
    releaseBound = true
  }

  function clearHold(): void {
    if (holdTimer != null) {
      clearInterval(holdTimer)
      holdTimer = null
    }
    holdStartedAt = 0
    isHoldingRefresh.value = false
    hardRefreshProgress.value = 0
    unbindReleaseListeners()
  }

  function goBack(): void {
    if (typeof window !== 'undefined' && window.history.length > 1) {
      router.back()
      return
    }
    void router.push('/app')
  }

  async function softRefresh(): Promise<void> {
    await queryClient.invalidateQueries()
  }

  function hardRefresh(): void {
    clearHold()
    window.location.reload()
  }

  function finishHold(): void {
    const wasHolding = isHoldingRefresh.value
    const progress = hardRefreshProgress.value
    clearHold()

    if (wasHolding && progress < 100) {
      void softRefresh()
    }
  }

  function onRefreshPointerDown(event: PointerEvent): void {
    if (event.button != null && event.button !== 0) {
      return
    }

    event.preventDefault()
    const target = event.currentTarget
    if (target instanceof HTMLElement) {
      try {
        target.setPointerCapture(event.pointerId)
      } catch {
        // Capture is optional; window-level release listeners still finish the gesture.
      }
    }

    clearHold()
    isHoldingRefresh.value = true
    holdStartedAt = Date.now()
    hardRefreshProgress.value = 0
    bindReleaseListeners()

    holdTimer = setInterval(() => {
      const elapsed = Date.now() - holdStartedAt
      hardRefreshProgress.value = Math.min(100, Math.round((elapsed / HOLD_MS) * 100))
      if (elapsed >= HOLD_MS) {
        hardRefresh()
      }
    }, 50)
  }

  function onRefreshPointerUp(): void {
    finishHold()
  }

  function onRefreshPointerLeave(event: PointerEvent): void {
    if (event.pointerType !== 'mouse') {
      return
    }
    clearHold()
  }

  function onRefreshPointerCancel(event: PointerEvent): void {
    if (event.pointerType === 'touch' || event.pointerType === 'pen') {
      return
    }
    clearHold()
  }

  onUnmounted(() => {
    clearHold()
  })

  return {
    goBack,
    softRefresh,
    hardRefreshProgress: readonly(hardRefreshProgress),
    isHoldingRefresh: readonly(isHoldingRefresh),
    onRefreshPointerDown,
    onRefreshPointerUp,
    onRefreshPointerLeave,
    onRefreshPointerCancel,
  }
}
