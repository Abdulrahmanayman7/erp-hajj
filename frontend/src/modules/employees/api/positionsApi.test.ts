import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as positionsApi from './positionsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('positionsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists positions with is_active filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, name: 'منسق', code: 'FIELD', is_active: true, created_at: null, updated_at: null }],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    })

    const result = await positionsApi.listPositions({ is_active: true, search: 'منسق' })

    expect(result.data[0]?.name).toBe('منسق')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/positions?is_active=true&search=%D9%85%D9%86%D8%B3%D9%82',
    )
  })

  it('creates, updates, activates, deactivates and deletes positions', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 2, name: 'مشرف', code: null, is_active: true },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 2, name: 'مشرف ميداني' },
    })
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: '',
      data: null,
    })

    await positionsApi.createPosition({ name: 'مشرف' })
    await positionsApi.updatePosition(2, { name: 'مشرف ميداني' })
    await positionsApi.activatePosition(2)
    await positionsApi.deactivatePosition(2)
    await positionsApi.deletePosition(2)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/positions', { name: 'مشرف' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/positions/2', { name: 'مشرف ميداني' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/positions/2/activate')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/positions/2/deactivate')
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/positions/2')
  })
})
