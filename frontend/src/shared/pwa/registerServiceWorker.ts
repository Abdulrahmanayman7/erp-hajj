import { applyStandaloneClass } from './displayMode'
import { PWA_SERVICE_WORKER_PATH } from './pwaVersion'

/** Options passed to `navigator.serviceWorker.register` (production). */
export const SERVICE_WORKER_REGISTER_OPTIONS = {
  scope: '/',
  updateViaCache: 'none',
} as const satisfies RegistrationOptions

/**
 * Register the shell service worker (production only).
 *
 * - updateViaCache: 'none' — bypass HTTP cache when fetching sw.js / imports
 * - registration.update() on load + visibility — detect new deploys without reload loops
 * - skipWaiting is handled inside sw.js; waiting workers are nudged via postMessage
 */
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
      .register(PWA_SERVICE_WORKER_PATH, SERVICE_WORKER_REGISTER_OPTIONS)
      .then((registration) => {
        const nudgeWaiting = (): void => {
          if (registration.waiting) {
            registration.waiting.postMessage({ type: 'SKIP_WAITING' })
          }
        }

        nudgeWaiting()
        void registration.update()

        registration.addEventListener('updatefound', () => {
          const worker = registration.installing
          if (!worker) {
            return
          }
          worker.addEventListener('statechange', () => {
            if (worker.state === 'installed') {
              nudgeWaiting()
            }
          })
        })

        document.addEventListener('visibilitychange', () => {
          if (document.visibilityState === 'visible') {
            void registration.update()
          }
        })
      })
      .catch(() => {
        // Registration failure must not break the ERP UI.
      })
  })
}
