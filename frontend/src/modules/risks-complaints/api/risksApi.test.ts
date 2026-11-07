import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiGet = vi.fn()
const apiPost = vi.fn()

vi.mock('@/shared/api/http', () => ({
  apiGet: (...args: unknown[]) => apiGet(...args),
  apiPost: (...args: unknown[]) => apiPost(...args),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '99' }),
  }),
}))

import { createRisk, getOperationalLinksCapabilities, getRiskTimeline, listRisks } from './risksApi'

describe('risksApi', () => {
  beforeEach(() => {
    apiGet.mockReset()
    apiPost.mockReset()
  })

  it('sends X-Season-Id on list', async () => {
    apiGet.mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } })
    await listRisks({ seasonId: 12, q: 'خطر' })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/risks?q=%D8%AE%D8%B7%D8%B1', {
      headers: { 'X-Season-Id': '12' },
    })
  })

  it('sends X-Season-Id on create', async () => {
    apiPost.mockResolvedValue({ data: { id: 1 } })
    await createRisk({ title: 'خطر' }, 5)
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/risks',
      { title: 'خطر' },
      { headers: { 'X-Season-Id': '5' } },
    )
  })

  it('loads risk timeline with season header', async () => {
    apiGet.mockResolvedValue({ data: [{ id: 1, event_type: 'RISK_CREATED' }] })
    const entries = await getRiskTimeline(44, 8)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/risks/44/timeline', {
      headers: { 'X-Season-Id': '8' },
    })
    expect(entries).toHaveLength(1)
  })

  it('loads operational links capabilities with season header', async () => {
    apiGet.mockResolvedValue({
      data: { season_projects_available: false, season_projects_reason: 'season_projects_api_unavailable' },
    })
    const caps = await getOperationalLinksCapabilities(3)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/operational-links/capabilities', {
      headers: { 'X-Season-Id': '3' },
    })
    expect(caps.season_projects_available).toBe(false)
  })
})
