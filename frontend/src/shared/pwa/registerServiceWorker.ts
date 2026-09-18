import { applyStandaloneClass } from './displayMode'

export function registerServiceWorker(): void {
  if (typeof window !== 'undefined') {
    applyStandaloneClass()
  }

  if (!import.meta.env.PROD) {
    return
  }
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
    return
  }

  window.addEventListener('load', () => {
    void navigator.serviceWorker
      .register('/sw.js', { scope: '/' })
      .then((registration) => {
        // Prefer the waiting worker as soon as a new build is available.
        if (registration.waiting) {
          registration.waiting.postMessage({ type: 'SKIP_WAITING' })
        }

        registration.addEventListener('updatefound', () => {
          const worker = registration.installing
          if (!worker) {
            return
          }
          worker.addEventListener('statechange', () => {
            if (worker.state === 'installed' && navigator.serviceWorker.controller) {
              // New content ready — activate on next navigation via skipWaiting in sw.js.
            }
          })
        })
      })
      .catch(() => {
        // Registration failure must not break the ERP UI.
      })
  })
}
