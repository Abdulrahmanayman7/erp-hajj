export type PullToRefreshPhase = 'idle' | 'pulling' | 'ready' | 'refreshing'

export const PULL_THRESHOLD_PX = 72
export const PULL_MAX_PX = 128
export const PULL_RESISTANCE = 0.42

export function applyPullResistance(deltaY: number, resistance = PULL_RESISTANCE, max = PULL_MAX_PX): number {
  if (deltaY <= 0) {
    return 0
  }
  return Math.min(max, deltaY * resistance)
}

export function phaseFromPull(pullPx: number, refreshing: boolean, threshold = PULL_THRESHOLD_PX): PullToRefreshPhase {
  if (refreshing) {
    return 'refreshing'
  }
  if (pullPx <= 0) {
    return 'idle'
  }
  return pullPx >= threshold ? 'ready' : 'pulling'
}

export function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) {
    return false
  }
  const el = target.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""], [role="textbox"]')
  return el != null
}

export function isOverlayBlockingPull(): boolean {
  if (typeof document === 'undefined') {
    return false
  }
  if (document.querySelector('[aria-modal="true"]')) {
    return true
  }
  if (document.querySelector('[data-pull-refresh-block="true"]')) {
    return true
  }
  // Open select / list menus (often teleported without aria-modal)
  if (document.querySelector('[role="listbox"], [role="menu"]')) {
    return true
  }
  return false
}

/**
 * Pull may start only when the scroll container (and any nested scrollers under the
 * touch target) are already at their top edge.
 */
export function canStartPullFromTarget(target: EventTarget | null, scroller: HTMLElement): boolean {
  if (scroller.scrollTop > 0) {
    return false
  }

  if (!(target instanceof Element)) {
    return true
  }

  if (target.closest('[data-no-pull-refresh]')) {
    return false
  }

  let el: Element | null = target
  while (el && el !== scroller) {
    if (el instanceof HTMLElement) {
      const style = window.getComputedStyle(el)
      const overflowY = style.overflowY
      const scrollable =
        (overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay') &&
        el.scrollHeight > el.clientHeight + 1
      if (scrollable && el.scrollTop > 0) {
        return false
      }
    }
    el = el.parentElement
  }

  return true
}

export function isTouchCapableDevice(): boolean {
  if (typeof window === 'undefined') {
    return false
  }
  return 'ontouchstart' in window || (navigator.maxTouchPoints ?? 0) > 0
}
