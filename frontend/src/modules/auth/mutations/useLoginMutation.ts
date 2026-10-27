import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { announceSessionBoundary, clearSession } from '../sessionIsolation'
import { assertSession, sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'

import { login } from '../api/authApi'
import { currentUserQueryKey } from '../queries/useCurrentUserQuery'
import type { LoginPayload } from '../types/auth'

function defaultHomeForUser(isPlatformUser: boolean): string {
  return isPlatformUser ? '/platform/tenants' : '/app'
}

export function useLoginMutation() {
  const queryClient = useQueryClient()
  const router = useRouter()

  let generation = sessionGeneration.value

  return useMutation({
    mutationFn: async (payload: LoginPayload) => {
      sessionTransition.value = true
      await clearSession(queryClient, true)
      generation = sessionGeneration.value
      const user = await login(payload)
      assertSession(generation)
      return user
    },
    onSettled: () => { if (generation === sessionGeneration.value) sessionTransition.value = false },
    onSuccess: async (user) => {
      assertSession(generation)
      announceSessionBoundary()
      queryClient.setQueryData(currentUserQueryKey, user)
      const defaultHome = defaultHomeForUser(user.is_platform_user)
      const redirect =
        typeof router.currentRoute.value.query.redirect === 'string'
          ? router.currentRoute.value.query.redirect
          : defaultHome

      let safeRedirect =
        redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : defaultHome

      // Soft-correct cross-area redirects after login.
      if (user.is_platform_user && (safeRedirect === '/app' || safeRedirect.startsWith('/app/'))) {
        safeRedirect = '/platform/tenants'
      }
      if (
        !user.is_platform_user &&
        (safeRedirect === '/platform' || safeRedirect.startsWith('/platform/'))
      ) {
        safeRedirect = '/app'
      }

      await router.replace(safeRedirect)
    },
  })
}
