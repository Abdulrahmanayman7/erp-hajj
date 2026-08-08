import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { login } from '../api/authApi'
import { currentUserQueryKey } from '../queries/useCurrentUserQuery'
import type { LoginPayload } from '../types/auth'

export function useLoginMutation() {
  const queryClient = useQueryClient()
  const router = useRouter()

  return useMutation({
    mutationFn: (payload: LoginPayload) => login(payload),
    onSuccess: async (user) => {
      queryClient.setQueryData(currentUserQueryKey, user)
      const redirect = typeof router.currentRoute.value.query.redirect === 'string'
        ? router.currentRoute.value.query.redirect
        : '/app'
      const safeRedirect = redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/app'
      await router.replace(safeRedirect)
    },
  })
}
