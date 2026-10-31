import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as pilgrimsApi from './pilgrimsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('pilgrimsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists pilgrims with search and pagination query', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, full_name: 'أحمد', gender: 'male', nationality: 'SA', phone: null, notes: null, created_at: null, updated_at: null }],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await pilgrimsApi.listPilgrims({ search: 'أحمد', page: 2, per_page: 15 })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.full_name).toBe('أحمد')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/pilgrims?search=%D8%A3%D8%AD%D9%85%D8%AF&page=2&per_page=15',
    )
  })

  it('gets, creates and updates a pilgrim', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 7, full_name: 'سارة', gender: 'female', nationality: null, phone: null, notes: null, created_at: null, updated_at: null },
    })
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 8, full_name: 'جديد' },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 7, full_name: 'محدث' },
    })

    await pilgrimsApi.getPilgrim(7)
    await pilgrimsApi.createPilgrim({ full_name: 'جديد', gender: 'male' })
    await pilgrimsApi.updatePilgrim(7, { full_name: 'محدث' })

    expect(apiGet).toHaveBeenCalledWith('/api/v1/pilgrims/7')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/pilgrims', { full_name: 'جديد', gender: 'male' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/pilgrims/7', { full_name: 'محدث' })
  })
})
