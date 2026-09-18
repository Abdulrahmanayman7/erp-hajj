export type DisplayMode = 'browser' | 'standalone'

/**
 * Detects installed / standalone display mode (Chromium + iOS Safari).
 */
export function isStandaloneDisplay(): boolean {
  if (typeof window === 'undefined') {
    return false
  }

  try {
    if (window.matchMedia('(display-mode: standalone)').matches) {
      return true
    }
  } catch {
    // ignore unsupported matchMedia
  }

  const nav = window.navigator as Navigator & { standalone?: boolean }
  if (nav.standalone === true) {
    return true
  }

  return false
}

export function getDisplayMode(): DisplayMode {
  return isStandaloneDisplay() ? 'standalone' : 'browser'
}

/**
 * Applies `is-standalone` on <html> for CSS hooks. Safe to call once at boot.
 */
export function applyStandaloneClass(root: HTMLElement = document.documentElement): boolean {
  const standalone = isStandaloneDisplay()
  root.classList.toggle('is-standalone', standalone)
  return standalone
}
