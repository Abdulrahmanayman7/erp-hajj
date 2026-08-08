import type { QueryClient } from '@tanstack/vue-query'
import type { NavigationGuard } from 'vue-router'

import { ApiError } from '@/shared/api/http'
import { fetchCurrentUser } from '@/modules/auth/api/authApi'
import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'
import { isAuthBlockCode, type AuthUser } from '@/modules/auth/types/auth'

export function createAuthGuard(queryClient: QueryClient): NavigationGuard {
  return async (to) => {
    const needsAuth = to.matched.some((record) => record.meta.requiresAuth === true)
    const guestOnly = to.matched.some((record) => record.meta.guestOnly === true)
    const requiredPermission = to.matched
      .map((record) => record.meta.permission as string | undefined)
      .filter(Boolean)
      .at(-1)

    if (!needsAuth && !guestOnly && !requiredPermission) {
      return true
    }

    let user = queryClient.getQueryData(currentUserQueryKey) as AuthUser | null | undefined

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

    if ((needsAuth || requiredPermission) && !authenticated) {
      return {
        name: 'login',
        query: to.fullPath !== '/app' ? { redirect: to.fullPath } : undefined,
      }
    }

    if (guestOnly && authenticated) {
      return { name: 'app-home' }
    }

    if (authenticated && requiredPermission) {
      const permissions = user?.permissions ?? []
      if (!permissions.includes(requiredPermission)) {
        if (to.name !== 'app-forbidden') {
          return { name: 'app-forbidden' }
        }
      }
    }

    return true
  }
}
