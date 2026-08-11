import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as api from './organizationUnitsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('organizationUnitsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists tree with default view=tree', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: undefined,
    })

    await api.listOrganizationUnits({ status: 'active', search: 'ops' })

    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/organization-units?view=tree&status=active&search=ops',
    )
  })

  it('creates an organization unit', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: {
        id: 1,
        name: 'Ops',
        code: 'OPS',
        type: 'department',
        status: 'active',
        parent_id: null,
        sort_order: 0,
        manager: null,
        children_count: 0,
        depth: 0,
        created_at: null,
        updated_at: null,
      },
      meta: undefined,
    })

    await api.createOrganizationUnit({
      name: 'Ops',
      code: 'OPS',
      type: 'department',
    })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/organization-units', {
      name: 'Ops',
      code: 'OPS',
      type: 'department',
    })
  })

  it('moves a unit', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 2 },
      meta: undefined,
    })

    await api.moveOrganizationUnit(2, 5)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/organization-units/2/move', {
      parent_id: 5,
    })
  })

  it('updates and deletes', async () => {
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1 },
      meta: undefined,
    })
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: '',
      data: null,
      meta: undefined,
    })

    await api.updateOrganizationUnit(1, { name: 'New' })
    await api.deleteOrganizationUnit(1)

    expect(apiPatch).toHaveBeenCalledWith('/api/v1/organization-units/1', { name: 'New' })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/organization-units/1')
  })
})
