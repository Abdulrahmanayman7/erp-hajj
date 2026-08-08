import { useQuery } from '@tanstack/vue-query'

import { ApiError } from '@/shared/api/http'

import { fetchCurrentUser } from '../api/authApi'
import { isAuthBlockCode } from '../types/auth'

export const currentUserQueryKey = ['auth', 'me'] as const

export function useCurrentUserQuery(enabled = true) {
  return useQuery({
    queryKey: currentUserQueryKey,
    queryFn: fetchCurrentUser,
    enabled,
    staleTime: 5 * 60 * 1000,
    retry: (failureCount, error) => {
      if (error instanceof ApiError) {
        if (error.status === 401 || error.status === 403 || isAuthBlockCode(error.code)) {
          return false
        }
      }
      return failureCount < 1
    },
    refetchOnWindowFocus: false,
  })
}
