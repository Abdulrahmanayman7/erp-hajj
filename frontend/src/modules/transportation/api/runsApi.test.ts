import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
}))

vi.mock('./seasonRequest', () => ({
  seasonRequestOptions: (seasonId?: number | null) =>
    seasonId != null ? { headers: { 'X-Season-Id': String(seasonId) } } : { headers: {} },
}))

import { apiGet, apiPost, apiPut } from '@/shared/api/http'

import {
  addRunPassengers,
  assignRunVehicle,
  cancelPassenger,
  listRunPassengers,
  transitionRun,
} from './runsApi'

describe('runsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPut).mockReset()
  })

  it('lists passengers and adds individuals', async () => {
    vi.mocked(apiGet).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 50, total: 0, last_page: 1 } })
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 3 } })
    await listRunPassengers(3, { page: 1 }, 7)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/transport-runs/3/passengers?page=1', {
      headers: { 'X-Season-Id': '7' },
    })
    await addRunPassengers(3, { source: 'individual', season_pilgrim_ids: [10, 11] }, 7)
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-runs/3/passengers',
      { source: 'individual', season_pilgrim_ids: [10, 11] },
      { headers: { 'X-Season-Id': '7' } },
    )
  })

  it('transitions run and substitutes vehicle with reason', async () => {
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 3 } })
    vi.mocked(apiPut).mockResolvedValue({ data: { id: 3 } })
    await transitionRun(3, 'start-boarding', 1)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/transport-runs/3/start-boarding', {}, {
      headers: { 'X-Season-Id': '1' },
    })
    await assignRunVehicle(3, { transport_season_vehicle_id: 9, reason: 'عطل فني' }, 1)
    expect(apiPut).toHaveBeenCalledWith(
      '/api/v1/transport-runs/3/vehicle',
      { transport_season_vehicle_id: 9, reason: 'عطل فني' },
      { headers: { 'X-Season-Id': '1' } },
    )
  })

  it('cancels passenger assignment', async () => {
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 44 } })
    await cancelPassenger(44, 'انسحاب', 1)
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-run-passengers/44/cancel',
      { reason: 'انسحاب' },
      { headers: { 'X-Season-Id': '1' } },
    )
  })
})
