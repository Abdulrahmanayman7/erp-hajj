import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

vi.mock('./seasonRequest', () => ({
  seasonRequestOptions: (seasonId?: number | null) =>
    seasonId != null ? { headers: { 'X-Season-Id': String(seasonId) } } : { headers: {} },
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import {
  createMovement,
  createRun,
  listMovements,
  planMovement,
  updateMovement,
} from './movementsApi'

describe('movementsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists movements with season header and status filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } })
    await listMovements({ status: 'draft', page: 1, seasonId: 9 })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/transport-movements?status=draft&page=1', {
      headers: { 'X-Season-Id': '9' },
    })
  })

  it('creates and updates movements', async () => {
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 1 } })
    vi.mocked(apiPatch).mockResolvedValue({ data: { id: 1 } })
    const form = {
      origin_point_id: 2,
      destination_point_id: 3,
      planned_departure_at: '2027-05-01T08:00:00',
      planned_arrival_at: '2027-05-01T10:00:00',
      notes: 'x',
    }
    await createMovement(form, 4)
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-movements',
      {
        origin_point_id: 2,
        destination_point_id: 3,
        planned_departure_at: '2027-05-01T08:00:00',
        planned_arrival_at: '2027-05-01T10:00:00',
        notes: 'x',
      },
      { headers: { 'X-Season-Id': '4' } },
    )
    await updateMovement(1, form, 4)
    expect(apiPatch).toHaveBeenCalled()
  })

  it('plans movement and creates run under movement', async () => {
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 8 } })
    await planMovement(5, 2)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/transport-movements/5/plan', {}, {
      headers: { 'X-Season-Id': '2' },
    })
    await createRun(
      5,
      { transport_season_vehicle_id: 11, capacity_override: '40', notes: '' },
      2,
    )
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-movements/5/runs',
      { transport_season_vehicle_id: 11, capacity_override: 40, notes: null },
      { headers: { 'X-Season-Id': '2' } },
    )
  })
})
