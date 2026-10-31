import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}))

vi.mock('./seasonRequest', () => ({
  seasonRequestOptions: (seasonId?: number | null) =>
    seasonId != null ? { headers: { 'X-Season-Id': String(seasonId) } } : { headers: {} },
}))

import { apiGet, apiPost } from '@/shared/api/http'

import {
  executeReplacement,
  listBreakdowns,
  previewReplacement,
  recordBreakdown,
} from './breakdownsApi'

describe('breakdownsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
  })

  it('lists and records breakdowns with season header', async () => {
    vi.mocked(apiGet).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } })
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 9 } })
    await listBreakdowns({ status: 'open', page: 1 }, 3)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/transport-breakdowns?status=open&page=1', {
      headers: { 'X-Season-Id': '3' },
    })
    await recordBreakdown(4, { reason: 'عطل فني واضح' }, 3)
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-runs/4/breakdowns',
      { reason: 'عطل فني واضح' },
      { headers: { 'X-Season-Id': '3' } },
    )
  })

  it('previews and executes replacement with reassignment plan', async () => {
    vi.mocked(apiGet).mockResolvedValue({ data: { capacity_gap: 1, plan_required: true } })
    vi.mocked(apiPost).mockResolvedValue({
      data: { breakdown: { id: 1 }, replacement: { id: 2 }, run: { id: 3 } },
    })
    await previewReplacement(1, 88, 5)
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/transport-breakdowns/1/preview-replacement?replacement_season_vehicle_id=88',
      { headers: { 'X-Season-Id': '5' } },
    )
    await executeReplacement(
      1,
      {
        replacement_season_vehicle_id: 88,
        reason: 'استبدال',
        idempotency_key: 'k1',
        reassignments: [{ run_passenger_id: 10, target_run_id: 20 }],
      },
      5,
    )
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-breakdowns/1/replace',
      {
        replacement_season_vehicle_id: 88,
        reason: 'استبدال',
        idempotency_key: 'k1',
        reassignments: [{ run_passenger_id: 10, target_run_id: 20 }],
      },
      { headers: { 'X-Season-Id': '5' } },
    )
  })
})
