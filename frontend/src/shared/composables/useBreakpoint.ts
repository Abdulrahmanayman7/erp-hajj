import { onMounted, onUnmounted, readonly, ref, type Ref } from 'vue'

/**
 * Central breakpoint helpers aligned with docs/ui/RESPONSIVE_UX.md.
 * Prefer Tailwind CSS for presentation; use this only when JS must branch.
 *
 * - Mobile: < 768px
 * - Tablet: 768–1279px
 * - Desktop: ≥ 1280px
 */
export interface UseBreakpointResult {
  width: Ref<number>
  isMobile: Ref<boolean>
  isTablet: Ref<boolean>
  isDesktop: Ref<boolean>
  /** true when permanent full sidebar should show (≥1280) */
  showFullSidebar: Ref<boolean>
  /** true when compact nav rail should show (768–1279) */
  showNavRail: Ref<boolean>
  /** true when bottom nav should show (<768) */
  showBottomNav: Ref<boolean>
}

const MOBILE_MAX = 767
const TABLET_MAX = 1279

function readWidth(): number {
  if (typeof window === 'undefined') {
    return 1280
  }
  return window.innerWidth
}

export function useBreakpoint(): UseBreakpointResult {
  const width = ref(readWidth())
  const isMobile = ref(width.value <= MOBILE_MAX)
  const isTablet = ref(width.value > MOBILE_MAX && width.value <= TABLET_MAX)
  const isDesktop = ref(width.value > TABLET_MAX)
  const showFullSidebar = ref(width.value > TABLET_MAX)
  const showNavRail = ref(width.value > MOBILE_MAX && width.value <= TABLET_MAX)
  const showBottomNav = ref(width.value <= MOBILE_MAX)

  function sync(): void {
    const w = readWidth()
    width.value = w
    isMobile.value = w <= MOBILE_MAX
    isTablet.value = w > MOBILE_MAX && w <= TABLET_MAX
    isDesktop.value = w > TABLET_MAX
    showFullSidebar.value = w > TABLET_MAX
    showNavRail.value = w > MOBILE_MAX && w <= TABLET_MAX
    showBottomNav.value = w <= MOBILE_MAX
  }

  let mobileMq: MediaQueryList | null = null
  let desktopMq: MediaQueryList | null = null

  function onChange(): void {
    sync()
  }

  onMounted(() => {
    sync()
    try {
      mobileMq = window.matchMedia(`(max-width: ${MOBILE_MAX}px)`)
      desktopMq = window.matchMedia(`(min-width: ${TABLET_MAX + 1}px)`)
      mobileMq.addEventListener('change', onChange)
      desktopMq.addEventListener('change', onChange)
    } catch {
      window.addEventListener('resize', onChange)
    }
  })

  onUnmounted(() => {
    mobileMq?.removeEventListener('change', onChange)
    desktopMq?.removeEventListener('change', onChange)
    window.removeEventListener('resize', onChange)
  })

  return {
    width: readonly(width),
    isMobile: readonly(isMobile),
    isTablet: readonly(isTablet),
    isDesktop: readonly(isDesktop),
    showFullSidebar: readonly(showFullSidebar),
    showNavRail: readonly(showNavRail),
    showBottomNav: readonly(showBottomNav),
  }
}
