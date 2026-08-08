import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { logout } from '../api/authApi'
import { currentUserQueryKey } from '../queries/useCurrentUserQuery'

export function useLogoutMutation() {
  const queryClient = useQueryClient()
  const router = useRouter()

  return useMutation({
    mutationFn: () => logout(),
    onSettled: async () => {
      queryClient.setQueryData(currentUserQueryKey, null)
      queryClient.removeQueries({ queryKey: currentUserQueryKey })
      await router.replace({ name: 'login' })
    },
  })
}
