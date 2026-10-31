import { beforeEach, describe, expect, it, vi } from 'vitest'

import { listSeasonPilgrims } from './seasonPilgrimsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '9' }),
  }),
}))

import { apiGet } from '@/shared/api/http'

describe('seasonPilgrimsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
  })

  it('lists season pilgrims with X-Season-Id and pilgrim filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await listSeasonPilgrims({ search: 'أحمد', pilgrim_id: 12, page: 1 }, 42)

    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-pilgrims?search=%D8%A3%D8%AD%D9%85%D8%AF&pilgrim_id=12&page=1', {
      headers: { 'X-Season-Id': '42' },
    })
  })

  it('falls back to accommodation season store headers when seasonId omitted', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await listSeasonPilgrims({ per_page: 10 })

    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-pilgrims?per_page=10', {
      headers: { 'X-Season-Id': '9' },
    })
  })
})
