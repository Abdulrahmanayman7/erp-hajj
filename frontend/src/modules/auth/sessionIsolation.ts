import type { QueryClient } from '@tanstack/vue-query'
import { getActivePinia } from 'pinia'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { advanceSession, sessionTransition } from '@/shared/api/sessionLifetime'
import { resetCsrfBootstrap } from '@/shared/api/http'
import { clearToasts } from '@/shared/composables/useToast'
import { clearConfirmation } from '@/shared/composables/useConfirm'

/** Whole-client eviction provides the user/tenant boundary for every module key.
 * Seasonal queries additionally use season ids. Never reuse a lifetime across accounts.
 */
const SESSION_BOUNDARY_KEY = 'erp-hajj.sessionBoundary'

export async function clearSession(client: QueryClient, broadcast = false): Promise<void> {
  advanceSession()
  const cancelled = client.cancelQueries()
  client.clear()
  resetCsrfBootstrap()
  if (getActivePinia()) useAccommodationSeasonStore().resetSession()
  clearToasts()
  clearConfirmation()
  try { localStorage.removeItem('erp-hajj.users.inviteMailUnavailable') } catch { /* storage unavailable */ }
  if (broadcast) announceSessionBoundary()
  await cancelled
}

export function announceSessionBoundary(): void {
  try { localStorage.setItem(SESSION_BOUNDARY_KEY, String(Date.now()) + ':' + Math.random()) } catch { /* storage unavailable */ }
}

/** Cookies are shared across tabs, but their query caches and Pinia instances are not. */
export function installSessionIsolation(client: QueryClient, navigate = () => window.location.replace('/login')): () => void {
  const reset = () => {
    sessionTransition.value = true
    void clearSession(client, false).finally(navigate)
  }
  const onStorage = (event: StorageEvent) => {
    if (event.key === SESSION_BOUNDARY_KEY && event.newValue !== null) reset()
  }
  const onPageShow = (event: PageTransitionEvent) => { if (event.persisted) reset() }
  window.addEventListener('storage', onStorage)
  window.addEventListener('pageshow', onPageShow)
  return () => {
    window.removeEventListener('storage', onStorage)
    window.removeEventListener('pageshow', onPageShow)
  }
}
