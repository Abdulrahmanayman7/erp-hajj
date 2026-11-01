import { QueryClient } from '@tanstack/vue-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createAuthGuard } from '@/app/guards/authGuard'
import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'

vi.mock('@/modules/auth/api/authApi', () => ({
  fetchCurrentUser: vi.fn(),
}))

function route(meta: Record<string, unknown>, path = '/app/purchase-requests/approval-inbox', name = 'purchase-requests-approval-inbox') {
  return {
    fullPath: path,
    path,
    name,
    matched: [{ meta, path, name }],
    meta,
    params: {},
    query: {},
    hash: '',
    redirectedFrom: undefined,
  } as never
}

describe('purchase-requests approval-inbox route guard', () => {
  let queryClient: QueryClient

  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: { queries: { retry: false } },
    })
  })

  it('blocks authenticated users without purchase_requests.approve', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Test',
      email: 't@example.com',
      status: 'active',
      is_platform_user: false,
      tenant: { id: 1, code: 'rafee', name: 'رفيع', status: 'active' },
      roles: [],
      permissions: ['purchase_requests.view'],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(
      route({ requiresAuth: true, permission: 'purchase_requests.approve' }),
      {} as never,
      (() => undefined) as never,
    )

    expect(result).toMatchObject({ name: 'app-forbidden' })
  })

  it('allows users with purchase_requests.approve', async () => {
    queryClient.setQueryData(currentUserQueryKey, {
      id: 1,
      name: 'Approver',
      email: 'a@example.com',
      status: 'active',
      is_platform_user: false,
      tenant: { id: 1, code: 'rafee', name: 'رفيع', status: 'active' },
      roles: [],
      permissions: ['purchase_requests.approve'],
    })

    const guard = createAuthGuard(queryClient)
    const result = await guard(
      route({ requiresAuth: true, permission: 'purchase_requests.approve' }),
      {} as never,
      (() => undefined) as never,
    )

    expect(result).toBe(true)
  })
})
