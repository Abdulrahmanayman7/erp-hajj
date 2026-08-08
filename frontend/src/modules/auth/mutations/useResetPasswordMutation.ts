import { useMutation } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'

import { resetPassword } from '../api/authApi'
import type { ResetPasswordPayload } from '../types/auth'

export function useResetPasswordMutation() {
  const router = useRouter()

  return useMutation({
    mutationFn: (payload: ResetPasswordPayload) => resetPassword(payload),
    onSuccess: async () => {
      await router.replace({ name: 'login', query: { reset: '1' } })
    },
  })
}
