import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as seasonsApi from './seasonsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('seasonsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists seasons and omits all status filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await seasonsApi.listSeasons({ status: 'all', page: 1 })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/seasons?page=1')

    await seasonsApi.listSeasons({ status: 'active' })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/seasons?status=active')
  })

  it('creates and updates seasons and runs lifecycle actions', async () => {
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: '', data: { id: 1 } })
    vi.mocked(apiPatch).mockResolvedValue({ success: true, message: '', data: { id: 1 } })

    await seasonsApi.createSeason({
      code: 'H1447',
      name: 'موسم 1447',
      hajj_year: 1447,
      operational_starts_on: '2025-05-01',
      operational_ends_on: '2025-07-01',
    })
    await seasonsApi.updateSeason(1, { name: 'محدّث' })
    await seasonsApi.activateSeason(1)
    await seasonsApi.closeSeason(1)
    await seasonsApi.reopenSeason(1, 'تصحيح تشغيلي')

    expect(apiPost).toHaveBeenCalledWith('/api/v1/seasons', expect.objectContaining({ code: 'H1447' }))
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/seasons/1', { name: 'محدّث' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/seasons/1/activate')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/seasons/1/close')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/seasons/1/reopen', { reason: 'تصحيح تشغيلي' })
  })
})
