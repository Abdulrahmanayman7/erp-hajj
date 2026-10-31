import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiGet = vi.fn()
const apiPost = vi.fn()
const apiPatch = vi.fn()
const apiDelete = vi.fn()

vi.mock('@/shared/api/http', () => ({
  apiGet: (...args: unknown[]) => apiGet(...args),
  apiPost: (...args: unknown[]) => apiPost(...args),
  apiPatch: (...args: unknown[]) => apiPatch(...args),
  apiDelete: (...args: unknown[]) => apiDelete(...args),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '7' }),
  }),
}))

describe('seasonFleetApi', () => {
  beforeEach(() => {
    vi.resetModules()
    apiGet.mockReset()
    apiPost.mockReset()
    apiPatch.mockReset()
    apiDelete.mockReset()
  })

  it('lists season vehicles with season header', async () => {
    apiGet.mockResolvedValue({
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })
    const { listSeasonVehicles } = await import('./seasonFleetApi')
    await listSeasonVehicles({ seasonId: 12, is_active: true })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/transport-season-vehicles?is_active=1', {
      headers: { 'X-Season-Id': '12' },
    })
  })

  it('creates a season vehicle link', async () => {
    apiPost.mockResolvedValue({ data: { id: 1 } })
    const { createSeasonVehicle } = await import('./seasonFleetApi')
    await createSeasonVehicle(
      {
        transport_vehicle_id: 5,
        operational_capacity: '40',
        service_level: 'standard',
        notes: '  note  ',
      },
      9,
    )
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/transport-season-vehicles',
      {
        transport_vehicle_id: 5,
        operational_capacity: 40,
        service_level: 'standard',
        notes: 'note',
      },
      { headers: { 'X-Season-Id': '9' } },
    )
  })

  it('unlinks with season header', async () => {
    apiDelete.mockResolvedValue({ data: null })
    const { deleteSeasonVehicle } = await import('./seasonFleetApi')
    await deleteSeasonVehicle(4, 9)
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/transport-season-vehicles/4', {
      headers: { 'X-Season-Id': '9' },
    })
  })
})
