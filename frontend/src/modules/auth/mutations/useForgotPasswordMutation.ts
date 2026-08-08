import { useMutation } from '@tanstack/vue-query'

import { forgotPassword } from '../api/authApi'
import type { ForgotPasswordPayload } from '../types/auth'

export function useForgotPasswordMutation() {
  return useMutation({
    mutationFn: (payload: ForgotPasswordPayload) => forgotPassword(payload),
  })
}
