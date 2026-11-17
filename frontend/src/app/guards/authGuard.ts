import { sessionGeneration } from '@/shared/api/sessionLifetime'
import { clearSession } from '@/modules/auth/sessionIsolation'
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

/** Skip a second /me round-trip when the cache was just refreshed for a denial check. */
const PERMISSION_REVALIDATE_COOLDOWN_MS = 10_000

function isPermissionDenied(
  permissions: string[],
  requiredPermission: string | undefined,
  requiredAnyPermissions: string[] | undefined,
  requiredAllPermissions: string[] | undefined,
): boolean {
  const deniedSingle =
    !!requiredPermission && !permissions.includes(requiredPermission)
  const deniedAny =
    !!requiredAnyPermissions &&
    !requiredAnyPermissions.some((permission) => permissions.includes(permission))
  const deniedAll =
    !!requiredAllPermissions &&
    !requiredAllPermissions.every((permission) => permissions.includes(permission))

  return deniedSingle || deniedAny || deniedAll
}

type PermissionRevalidateResult =
  | { kind: 'user'; user: AuthUser }
  | { kind: 'aborted' }
  | { kind: 'unauthenticated' }
  | { kind: 'blocked'; code: string }

/**
 * When cached permissions would deny navigation, re-fetch /auth/me once so freshly
 * granted server permissions are not blocked by a stale TanStack Query cache.
 * Respects sessionGeneration; cooldown prevents refresh loops on true denials.
 */
async function revalidateCurrentUserForPermissions(
  queryClient: QueryClient,
  cached: AuthUser,
): Promise<PermissionRevalidateResult> {
  const state = queryClient.getQueryState(currentUserQueryKey)
  if (
    state?.dataUpdatedAt &&
    Date.now() - state.dataUpdatedAt < PERMISSION_REVALIDATE_COOLDOWN_MS
  ) {
    return { kind: 'user', user: cached }
  }

  const generation = sessionGeneration.value
  try {
    const fresh = await queryClient.fetchQuery({
      queryKey: currentUserQueryKey,
      queryFn: fetchCurrentUser,
      staleTime: 0,
      retry: false,
    })
    if (generation !== sessionGeneration.value) return { kind: 'aborted' }
    return { kind: 'user', user: fresh }
  } catch (error) {
    if (generation !== sessionGeneration.value) return { kind: 'aborted' }

    if (error instanceof ApiError && (error.status === 401 || isAuthBlockCode(error.code))) {
      const setupStatus = queryClient.getQueryData(platformSetupStatusQueryKey)
      await clearSession(queryClient, false, { advance: generation > 0 })
      if (setupStatus !== undefined) {
        queryClient.setQueryData(platformSetupStatusQueryKey, setupStatus)
      }
      queryClient.setQueryData(currentUserQueryKey, null)

      if (isAuthBlockCode(error.code)) {
        queryClient.removeQueries({ queryKey: currentUserQueryKey })
        return { kind: 'blocked', code: error.code }
      }

      return { kind: 'unauthenticated' }
    }

    // Network / transient failure: fail closed on the cached denial (no bypass).
    return { kind: 'user', user: cached }
  }
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
    const requiredAnyPermissions = to.matched
      .map((record) => record.meta.permissionsAny as string[] | undefined)
      .filter((value): value is string[] => Array.isArray(value) && value.length > 0)
      .at(-1)
    const requiredAllPermissions = to.matched
      .map((record) => record.meta.permissionsAll as string[] | undefined)
      .filter((value): value is string[] => Array.isArray(value) && value.length > 0)
      .at(-1)

    if (
      !needsAuth &&
      !guestOnly &&
      !requiredPermission &&
      !requiredAnyPermissions &&
      !requiredAllPermissions &&
      !isSetupRoute
    ) {
      return true
    }

    let user = queryClient.getQueryData(currentUserQueryKey) as AuthUser | null | undefined

    if (user === undefined) {
      const generation = sessionGeneration.value
      try {
        user = await queryClient.fetchQuery({
          queryKey: currentUserQueryKey,
          queryFn: fetchCurrentUser,
          staleTime: 5 * 60 * 1000,
          retry: false,
        })
        if (generation !== sessionGeneration.value) return false
      } catch (error) {
        if (generation !== sessionGeneration.value) return false
        const setupStatus = queryClient.getQueryData(platformSetupStatusQueryKey)
        // First anonymous `/me` 401: clear caches without advancing sessionGeneration
        // (avoids remounting AppLayout while the login navigation is still settling).
        await clearSession(queryClient, false, { advance: generation > 0 })
        // Public bootstrap availability is not account data.
        if (setupStatus !== undefined) queryClient.setQueryData(platformSetupStatusQueryKey, setupStatus)
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

    if (
      (needsAuth || requiredPermission || requiredAnyPermissions || requiredAllPermissions) &&
      !authenticated
    ) {
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

    if (authenticated && user) {
      const permissions = user.permissions ?? []

      if (
        isPermissionDenied(
          permissions,
          requiredPermission,
          requiredAnyPermissions,
          requiredAllPermissions,
        )
      ) {
        const refreshed = await revalidateCurrentUserForPermissions(queryClient, user)
        if (refreshed.kind === 'aborted') return false

        if (refreshed.kind === 'blocked') {
          if (to.name !== 'access-blocked') {
            return { name: 'access-blocked', query: { code: refreshed.code } }
          }
          return true
        }

        if (refreshed.kind === 'unauthenticated') {
          return {
            name: 'login',
            query:
              to.fullPath !== '/app' && to.fullPath !== '/platform/tenants'
                ? { redirect: to.fullPath }
                : undefined,
          }
        }

        user = refreshed.user
        const freshPermissions = user.permissions ?? []
        const stillDenied = isPermissionDenied(
          freshPermissions,
          requiredPermission,
          requiredAnyPermissions,
          requiredAllPermissions,
        )

        if (stillDenied) {
          if (user.is_platform_user) {
            // Soft: stay in platform area without a dedicated 403 page yet.
            if (to.name !== 'platform-tenants') {
              return { name: 'platform-tenants' }
            }
          } else if (to.name !== 'app-forbidden') {
            return { name: 'app-forbidden' }
          }
        }
      }
    }

    return true
  }
}
