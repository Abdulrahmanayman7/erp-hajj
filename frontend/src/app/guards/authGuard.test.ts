import { QueryClient } from '@tanstack/vue-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/shared/api/http'
import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'
import { platformSetupStatusQueryKey } from '@/modules/platform/queries/usePlatformSetupStatusQuery'

import { createAuthGuard } from './authGuard'

vi.mock('@/modules/auth/api/authApi', () => ({
  fetchCurrentUser: vi.fn(),
}))

vi.mock('@/modules/platform/api/platformSetupApi', () => ({
  fetchPlatformSetupStatus: vi.fn(),
}))

import { fetchCurrentUser } from '@/modules/auth/api/authApi'
import { fetchPlatformSetupStatus } from '@/modules/platform/api/platformSetupApi'

function route(
  meta: Record<string, unknown>,
  fullPath = '/app',
  name = 'app-home',
  path = fullPath,
) {
  return {
    matched: [{ meta }],
    fullPath,
    path,
    name,
    query: {},
  } as never
}

describe('createAuthGuard', () => {
  let queryClient: QueryClient

  beforeEach(() => {
    queryClient = new QueryClient()
    vi.mocked(fetchCurrentUser).mockReset()
    vi.mocked(fetchPlatformSetupStatus).mockReset()
    vi.mocked(fetchPlatformSetupStatus).mockResolvedValue({ available: false })
  })

  it('redirects unauthenticated users from protected routes to login', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(401, {
      success: false,
      message: 'unauth',
      code: 'AUTH_UNAUTHENTICATED',
    }))

    const guard = createAuthGuard(queryClient)
    const result = await guard(route({ requiresAuth: true }), {} as never, (() => undefined) as never)

    expect(result).toMatchObject({ name: 'login' })
  })

  it('redirects authenticated users away from guest routes', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Test',
      email: 't@example.com',
      status: 'active',
      is_platform_user: false,
      tenant: null,
      roles: [],
      permissions: [],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(route({ guestOnly: true }, '/login', 'login'), {} as never, (() => undefined) as never)

    expect(result).toMatchObject({ name: 'app-home' })
  })

  it('redirects authenticated platform users from guest routes to platform tenants', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Platform',
      email: 'p@example.com',
      status: 'active',
      is_platform_user: true,
      tenant: null,
      roles: [],
      permissions: ['platform_tenants.view'],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(route({ guestOnly: true }, '/login', 'login'), {} as never, (() => undefined) as never)

    expect(result).toMatchObject({ name: 'platform-tenants' })
  })

  it('redirects guests to setup when bootstrap is available', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(401, {
      success: false,
      message: 'unauth',
      code: 'AUTH_UNAUTHENTICATED',
    }))
    queryClient.setQueryData(platformSetupStatusQueryKey, { available: true })

    const guard = createAuthGuard(queryClient)
    const result = await guard(route({ guestOnly: true }, '/login', 'login'), {} as never, (() => undefined) as never)

    expect(result).toMatchObject({ name: 'platform-setup' })
  })

  it('preserves a safe redirect path', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(401, {
      success: false,
      message: 'unauth',
      code: 'AUTH_UNAUTHENTICATED',
    }))

    const guard = createAuthGuard(queryClient)
    const result = await guard(
      route({ requiresAuth: true }, '/app/settings', 'settings'),
      {} as never,
      (() => undefined) as never,
    )

    expect(result).toMatchObject({ name: 'login', query: { redirect: '/app/settings' } })
  })

  it('routes blocked accounts to access-blocked without retry storms', async () => {
    vi.mocked(fetchCurrentUser).mockRejectedValue(new ApiError(403, {
      success: false,
      message: 'suspended',
      code: 'TENANT_SUSPENDED',
    }))

    const guard = createAuthGuard(queryClient)
    const result = await guard(route({ requiresAuth: true }), {} as never, (() => undefined) as never)

    expect(result).toMatchObject({ name: 'access-blocked', query: { code: 'TENANT_SUSPENDED' } })
    expect(fetchCurrentUser).toHaveBeenCalledTimes(1)
  })

  it('sends authenticated users lacking permission to 403 page', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Test',
      email: 't@example.com',
      status: 'active',
      is_platform_user: false,
      tenant: null,
      roles: [],
      permissions: ['dashboard.view'],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(
      route({ requiresAuth: true, permission: 'users.view' }, '/app/users', 'users'),
      {} as never,
      (() => undefined) as never,
    )

    expect(result).toMatchObject({ name: 'app-forbidden' })
  })

  it('soft-redirects platform users away from tenant app routes', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Platform',
      email: 'p@example.com',
      status: 'active',
      is_platform_user: true,
      tenant: null,
      roles: [],
      permissions: ['platform_tenants.view'],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(
      route({ requiresAuth: true, permission: 'dashboard.view' }, '/app', 'app-home', '/app'),
      {} as never,
      (() => undefined) as never,
    )

    expect(result).toMatchObject({ name: 'platform-tenants' })
  })

  it('blocks tenant users from platform routes', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Tenant',
      email: 't@example.com',
      status: 'active',
      is_platform_user: false,
      tenant: null,
      roles: [],
      permissions: ['dashboard.view'],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(
      route(
        { requiresAuth: true, permission: 'platform_tenants.view' },
        '/platform/tenants',
        'platform-tenants',
        '/platform/tenants',
      ),
      {} as never,
      (() => undefined) as never,
    )

    expect(result).toMatchObject({ name: 'app-home' })
  })
})
