import { computed, onUnmounted, ref, watch, type Ref } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'

import { isDocumentScrollLocked } from '@/shared/composables/useBodyScrollLock'
import { useBreakpoint } from '@/shared/composables/useBreakpoint'
import {
  applyPullResistance,
  canStartPullFromTarget,
  isEditableTarget,
  isOverlayBlockingPull,
  isTouchCapableDevice,
  phaseFromPull,
  PULL_MAX_PX,
  PULL_THRESHOLD_PX,
  type PullToRefreshPhase,
} from '@/shared/utils/pullToRefresh'

type PullToRefreshOptions = {
  /** Soft refresh via TanStack Query. Defaults to invalidate + refetch active queries. */
  onRefresh?: () => Promise<void>
  /** Optional hard reload fallback when soft refresh throws. */
  hardReloadFallback?: boolean
}

export function usePullToRefresh(
  scroller: Ref<HTMLElement | null>,
  options: PullToRefreshOptions = {},
) {
  const queryClient = useQueryClient()
  const { isDesktop } = useBreakpoint()

  const pullPx = ref(0)
  const refreshing = ref(false)
  const animatingOut = ref(false)

  const enabled = computed(() => !isDesktop.value && isTouchCapableDevice())
  const phase = computed<PullToRefreshPhase>(() => phaseFromPull(pullPx.value, refreshing.value))
  const progress = computed(() => Math.min(1, pullPx.value / PULL_THRESHOLD_PX))
  const visible = computed(() => pullPx.value > 0 || refreshing.value || animatingOut.value)

  let tracking = false
  let startY = 0
  let startX = 0
  let pointerId: number | null = null
  let pullingActive = false

  function syncScrollerOffset(): void {
    const el = scroller.value
    if (!el) {
      return
    }
    el.style.setProperty('--pull-refresh-offset', `${pullPx.value}px`)
    const transitioning = refreshing.value || animatingOut.value || pullPx.value === 0
    el.style.setProperty('--pull-refresh-transition', transitioning ? 'transform 200ms ease' : 'none')
  }

  watch([pullPx, refreshing, animatingOut], () => {
    syncScrollerOffset()
  })

  async function runSoftRefresh(): Promise<void> {
    if (options.onRefresh) {
      await options.onRefresh()
      return
    }
    await queryClient.invalidateQueries()
    await queryClient.refetchQueries({ type: 'active' })
  }

  function resetPull(animate: boolean): void {
    if (!animate || pullPx.value <= 0) {
      pullPx.value = 0
      animatingOut.value = false
      return
    }
    animatingOut.value = true
    pullPx.value = 0
    window.setTimeout(() => {
      animatingOut.value = false
    }, 220)
  }

  async function triggerRefresh(): Promise<void> {
    if (refreshing.value) {
      return
    }
    refreshing.value = true
    pullPx.value = PULL_THRESHOLD_PX
    try {
      await runSoftRefresh()
    } catch {
      if (options.hardReloadFallback) {
        window.location.reload()
        return
      }
    } finally {
      refreshing.value = false
      resetPull(true)
    }
  }

  function blocked(): boolean {
    if (!enabled.value || refreshing.value) {
      return true
    }
    if (isDocumentScrollLocked() || isOverlayBlockingPull()) {
      return true
    }
    return false
  }

  function onPointerDown(event: PointerEvent): void {
    if (event.pointerType !== 'touch' && event.pointerType !== 'pen') {
      return
    }
    if (blocked()) {
      return
    }
    const el = scroller.value
    if (!el) {
      return
    }
    if (isEditableTarget(event.target)) {
      return
    }
    if (!canStartPullFromTarget(event.target, el)) {
      return
    }

    tracking = true
    pullingActive = false
    pointerId = event.pointerId
    startY = event.clientY
    startX = event.clientX
  }

  function onPointerMove(event: PointerEvent): void {
    if (!tracking || pointerId !== event.pointerId) {
      return
    }
    const el = scroller.value
    if (!el || blocked()) {
      tracking = false
      pullingActive = false
      resetPull(false)
      return
    }

    const dy = event.clientY - startY
    const dx = Math.abs(event.clientX - startX)

    if (!pullingActive) {
      if (dy < 8) {
        return
      }
      // Horizontal swipe / carousel → abandon
      if (dx > dy) {
        tracking = false
        return
      }
      if (el.scrollTop > 0 || !canStartPullFromTarget(event.target, el)) {
        tracking = false
        return
      }
      pullingActive = true
    }

    if (dy <= 0) {
      pullPx.value = 0
      return
    }

    event.preventDefault()
    pullPx.value = applyPullResistance(dy)
  }

  function onPointerUp(event: PointerEvent): void {
    if (!tracking || pointerId !== event.pointerId) {
      return
    }
    tracking = false
    pointerId = null

    if (!pullingActive) {
      return
    }
    pullingActive = false

    const shouldRefresh = pullPx.value >= PULL_THRESHOLD_PX && !refreshing.value
    if (shouldRefresh) {
      void triggerRefresh()
      return
    }
    resetPull(true)
  }

  function onPointerCancel(event: PointerEvent): void {
    if (pointerId != null && event.pointerId !== pointerId) {
      return
    }
    tracking = false
    pullingActive = false
    pointerId = null
    if (!refreshing.value) {
      resetPull(true)
    }
  }

  function bind(el: HTMLElement): void {
    el.classList.add('app-pull-refresh-host')
    syncScrollerOffset()
    el.addEventListener('pointerdown', onPointerDown, { passive: true })
    el.addEventListener('pointermove', onPointerMove, { passive: false })
    el.addEventListener('pointerup', onPointerUp, { passive: true })
    el.addEventListener('pointercancel', onPointerCancel, { passive: true })
  }

  function unbind(el: HTMLElement): void {
    el.classList.remove('app-pull-refresh-host')
    el.style.removeProperty('--pull-refresh-offset')
    el.style.removeProperty('--pull-refresh-transition')
    el.removeEventListener('pointerdown', onPointerDown)
    el.removeEventListener('pointermove', onPointerMove)
    el.removeEventListener('pointerup', onPointerUp)
    el.removeEventListener('pointercancel', onPointerCancel)
  }

  watch(
    scroller,
    (el, prev) => {
      if (prev) {
        unbind(prev)
      }
      if (el) {
        bind(el)
      }
    },
    { immediate: true },
  )

  onUnmounted(() => {
    if (scroller.value) {
      unbind(scroller.value)
    }
  })

  return {
    enabled,
    phase,
    pullPx,
    progress,
    refreshing,
    visible,
    maxPullPx: PULL_MAX_PX,
    thresholdPx: PULL_THRESHOLD_PX,
    triggerRefresh,
  }
}
