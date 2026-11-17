/**
 * Scroll a target into view within a specific overflow container without
 * scrolling the document or unrelated ancestors.
 */
export function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false
  }
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

export function computeScrollTopToReveal(
  container: HTMLElement,
  target: HTMLElement,
  padding = 12,
): number {
  const containerRect = container.getBoundingClientRect()
  const targetRect = target.getBoundingClientRect()
  const current = container.scrollTop

  const topGap = targetRect.top - containerRect.top
  const bottomGap = targetRect.bottom - containerRect.bottom

  if (topGap < padding) {
    return Math.max(0, current + topGap - padding)
  }
  if (bottomGap > -padding) {
    return Math.max(0, current + bottomGap + padding)
  }
  return current
}

export function scrollContainerIntoView(
  container: HTMLElement | null | undefined,
  target: HTMLElement | null | undefined,
  options: { padding?: number; behavior?: ScrollBehavior } = {},
): void {
  if (!container || !target || !container.contains(target)) return

  const nextTop = computeScrollTopToReveal(container, target, options.padding ?? 12)
  if (Math.abs(nextTop - container.scrollTop) < 1) return

  const behavior =
    options.behavior ?? (prefersReducedMotion() ? 'auto' : 'smooth')

  container.scrollTo({ top: nextTop, behavior })
}
