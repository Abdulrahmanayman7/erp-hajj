import type { QueryClient } from '@tanstack/vue-query'
import type { NavigationGuard } from 'vue-router'

import { ApiError } from '@/shared/api/http'
import { fetchCurrentUser } from '@/modules/auth/api/authApi'
import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'
import { isAuthBlockCode, type AuthUser } from '@/modules/auth/types/auth'
import { fetchPlatformSetupStatus } from '@/modules/platform/api/platformSetupApi'
import { platformSetupStatusQueryKey } from '@/modules/platform/queries/usePlatformSetupStatusQuery'

function isPlatformPath(path: string): boolean {
  return path === '/platform' || path.startsWith('/platform/')
}

function isAppPath(path: string): boolean {
  return path === '/app' || path.startsWith('/app/')
}

function homeForUser(user: AuthUser) {
  return user.is_platform_user
    ? { name: 'platform-tenants' as const }
    : { name: 'app-home' as const }
}

async function resolveSetupAvailable(queryClient: QueryClient): Promise<boolean | null> {
  const cached = queryClient.getQueryData(platformSetupStatusQueryKey) as
    | { available: boolean }
    | undefined

  if (cached) {
    return cached.available
  }

  try {
    const status = await queryClient.fetchQuery({
      queryKey: platformSetupStatusQueryKey,
      queryFn: fetchPlatformSetupStatus,
      staleTime: 60 * 1000,
      retry: false,
    })
    return status.available
  } catch {
    return null
  }
}

export function createAuthGuard(queryClient: QueryClient): NavigationGuard {
  return async (to) => {
    const needsAuth = to.matched.some((record) => record.meta.requiresAuth === true)
    const guestOnly = to.matched.some((record) => record.meta.guestOnly === true)
    const isSetupRoute = to.name === 'platform-setup'
    const requiredPermission = to.matched
      .map((record) => record.meta.permission as string | undefined)
      .filter(Boolean)
      .at(-1)

    if (!needsAuth && !guestOnly && !requiredPermission && !isSetupRoute) {
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

    // First-time platform bootstrap: force guests to /setup when available.
    if (!authenticated && (guestOnly || isSetupRoute)) {
      const setupAvailable = await resolveSetupAvailable(queryClient)

      if (setupAvailable === true && !isSetupRoute) {
        return { name: 'platform-setup' }
      }

      if (setupAvailable === false && isSetupRoute) {
        return { name: 'login' }
      }
    }

    if ((needsAuth || requiredPermission) && !authenticated) {
      return {
        name: 'login',
        query: to.fullPath !== '/app' && to.fullPath !== '/platform/tenants'
          ? { redirect: to.fullPath }
          : undefined,
      }
    }

    if (guestOnly && authenticated && user) {
      return homeForUser(user)
    }

    if (authenticated && user) {
      if (user.is_platform_user && isAppPath(to.path) && to.name !== 'app-forbidden') {
        return { name: 'platform-tenants' }
      }

      if (!user.is_platform_user && isPlatformPath(to.path)) {
        return { name: 'app-home' }
      }
    }

    if (authenticated && requiredPermission) {
      const permissions = user?.permissions ?? []
      if (!permissions.includes(requiredPermission)) {
        if (user?.is_platform_user) {
          // Soft: stay in platform area without a dedicated 403 page yet.
          if (to.name !== 'platform-tenants') {
            return { name: 'platform-tenants' }
          }
        } else if (to.name !== 'app-forbidden') {
          return { name: 'app-forbidden' }
        }
      }
    }

    return true
  }
}
