import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { logout } from '../api/authApi'
import { clearSession } from '../sessionIsolation'
import { sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'

export function useLogoutMutation() {
  const queryClient = useQueryClient()
  const router = useRouter()

  let generation = sessionGeneration.value

  return useMutation({
    mutationFn: async () => {
      sessionTransition.value = true
      await clearSession(queryClient, true)
      generation = sessionGeneration.value
      return logout()
    },
    onSettled: async () => {
      if (generation !== sessionGeneration.value) return
      await clearSession(queryClient, true)
      queryClient.setQueryData(['auth', 'me'], null)
      try { await router.replace({ name: 'login' }) }
      finally { sessionTransition.value = false }
    },
  })
}
