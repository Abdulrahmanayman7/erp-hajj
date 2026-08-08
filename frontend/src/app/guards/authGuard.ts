import type { QueryClient } from '@tanstack/vue-query'
import type { NavigationGuard } from 'vue-router'

import { ApiError } from '@/shared/api/http'
import { fetchCurrentUser } from '@/modules/auth/api/authApi'
import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'
import { isAuthBlockCode } from '@/modules/auth/types/auth'

export function createAuthGuard(queryClient: QueryClient): NavigationGuard {
  return async (to) => {
    const needsAuth = to.matched.some((record) => record.meta.requiresAuth === true)
    const guestOnly = to.matched.some((record) => record.meta.guestOnly === true)

    if (!needsAuth && !guestOnly) {
      return true
    }

    let user = queryClient.getQueryData(currentUserQueryKey) as Awaited<
      ReturnType<typeof fetchCurrentUser>
    > | null | undefined

    if (user === undefined) {
      try {
        user = await queryClient.fetchQuery({
          queryKey: currentUserQueryKey,
          queryFn: fetchCurrentUser,
          staleTime: 5 * 60 * 1000,
          retry: false,
        })
      } catch (error) {
        queryClient.setQueryData(currentUserQueryKey, null)

        if (error instanceof ApiError && isAuthBlockCode(error.code)) {
          queryClient.removeQueries({ queryKey: currentUserQueryKey })
          if (to.name !== 'access-blocked') {
            return { name: 'access-blocked', query: { code: error.code } }
          }
          return true
        }

        user = null
      }
    }

    const authenticated = !!user

    if (needsAuth && !authenticated) {
      return {
        name: 'login',
        query: to.fullPath !== '/app' ? { redirect: to.fullPath } : undefined,
      }
    }

    if (guestOnly && authenticated) {
      return { name: 'app-home' }
    }

    return true
  }
}
