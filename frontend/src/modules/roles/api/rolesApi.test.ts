import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as rolesApi from './rolesApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiGet, apiPut } from '@/shared/api/http'

describe('rolesApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPut).mockReset()
  })

  it('loads grouped permission catalog', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: {
        modules: [
          {
            module: 'users',
            display_name: 'المستخدمون',
            permissions: [{ id: 1, name: 'users.view', display_name: 'عرض', description: null }],
          },
        ],
      },
    })

    const modules = await rolesApi.listPermissionCatalog()
    expect(modules[0]?.module).toBe('users')
    expect(apiGet).toHaveBeenCalledWith('/api/v1/permissions')
  })

  it('syncs role permissions', async () => {
    vi.mocked(apiPut).mockResolvedValue({
      success: true,
      message: '',
      data: {
        id: 5,
        name: 'Ops',
        code: 'ops',
        description: null,
        is_system: false,
        is_active: true,
        users_count: 0,
        permissions_count: 2,
        permissions: ['users.view', 'dashboard.view'],
        created_at: null,
        updated_at: null,
      },
    })

    await rolesApi.syncRolePermissions(5, [1, 2])
    expect(apiPut).toHaveBeenCalledWith('/api/v1/roles/5/permissions', { permission_ids: [1, 2] })
  })
})
