import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { login } from '../api/authApi'
import { currentUserQueryKey } from '../queries/useCurrentUserQuery'
import type { LoginPayload } from '../types/auth'

function defaultHomeForUser(isPlatformUser: boolean): string {
  return isPlatformUser ? '/platform/tenants' : '/app'
}

export function useLoginMutation() {
  const queryClient = useQueryClient()
  const router = useRouter()

  return useMutation({
    mutationFn: (payload: LoginPayload) => login(payload),
    onSuccess: async (user) => {
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
