import type { QueryClient } from '@tanstack/vue-query'
import { getActivePinia } from 'pinia'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { advanceSession, sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'
import { resetCsrfBootstrap } from '@/shared/api/http'
import { clearToasts } from '@/shared/composables/useToast'
import { clearConfirmation } from '@/shared/composables/useConfirm'

/** Whole-client eviction provides the user/tenant boundary for every module key.
 * Seasonal queries additionally use season ids. Never reuse a lifetime across accounts.
 */
const SESSION_BOUNDARY_KEY = 'erp-hajj.sessionBoundary'

export async function clearSession(
  client: QueryClient,
  broadcast = false,
  options: { advance?: boolean } = {},
): Promise<void> {
  // Anonymous `/me` failures must not advance the generation: that remounts the
  // authenticated app shell mid-navigation and can leave `/login` stuck without GuestLayout.
  if (options.advance !== false) {
    advanceSession()
  }
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

/**
 * Cookies are shared across tabs, but their query caches and Pinia instances are not.
 *
 * BFCache (`pageshow` + `persisted`) can briefly paint the previous authenticated tree.
 * Re-validate `/me` against the live cookie instead of forcing logout — forced logout was
 * causing dashboard → login flicker on refresh / back-forward restore.
 */
export function installSessionIsolation(client: QueryClient, navigate = () => window.location.replace('/login')): () => void {
  const reset = () => {
    sessionTransition.value = true
    void clearSession(client, false).finally(navigate)
  }
  const onStorage = (event: StorageEvent) => {
    if (event.key === SESSION_BOUNDARY_KEY && event.newValue !== null) reset()
  }
  const onPageShow = (event: PageTransitionEvent) => {
    if (!event.persisted) return
    const generation = sessionGeneration.value
    void import('@/modules/auth/api/authApi').then(({ fetchCurrentUser }) =>
      import('@/modules/auth/queries/useCurrentUserQuery').then(({ currentUserQueryKey }) =>
        client
          .fetchQuery({
            queryKey: currentUserQueryKey,
            queryFn: fetchCurrentUser,
            staleTime: 0,
            retry: false,
          })
          .then((user) => {
            if (generation !== sessionGeneration.value) return
            client.setQueryData(currentUserQueryKey, user)
          })
          .catch(() => {
            if (generation !== sessionGeneration.value) return
            reset()
          }),
      ),
    )
  }
  window.addEventListener('storage', onStorage)
  window.addEventListener('pageshow', onPageShow)
  return () => {
    window.removeEventListener('storage', onStorage)
    window.removeEventListener('pageshow', onPageShow)
  }
}
