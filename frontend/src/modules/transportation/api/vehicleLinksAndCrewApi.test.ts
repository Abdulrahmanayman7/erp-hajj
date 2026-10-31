import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiGet = vi.fn()
const apiPost = vi.fn()
const apiPatch = vi.fn()
const apiPut = vi.fn()
const apiDelete = vi.fn()

vi.mock('@/shared/api/http', () => ({
  apiGet: (...args: unknown[]) => apiGet(...args),
  apiPost: (...args: unknown[]) => apiPost(...args),
  apiPatch: (...args: unknown[]) => apiPatch(...args),
  apiPut: (...args: unknown[]) => apiPut(...args),
  apiDelete: (...args: unknown[]) => apiDelete(...args),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '7' }),
  }),
}))

describe('vehicle links + default crew APIs', () => {
  beforeEach(() => {
    vi.resetModules()
    ;[apiGet, apiPost, apiPatch, apiPut, apiDelete].forEach((fn) => fn.mockReset())
  })

  it('updates vehicle asset/contract links via PUT', async () => {
    apiPut.mockResolvedValue({
      data: { id: 2, asset_id: 9, contract_id: null },
    })
    const { updateTransportVehicleLinks } = await import('./vehiclesApi')
    await updateTransportVehicleLinks(2, { asset_id: 9, contract_id: null })
    expect(apiPut).toHaveBeenCalledWith('/api/v1/transport-vehicles/2/links', {
      asset_id: 9,
      contract_id: null,
    })
  })

  it('sets default crew with season header', async () => {
    apiPut.mockResolvedValue({ data: { id: 4 } })
    const { setSeasonVehicleDefaultCrew } = await import('./seasonFleetApi')
    await setSeasonVehicleDefaultCrew(
      4,
      {
        default_driver_employee_id: 11,
        default_external_driver_id: null,
        default_supervisor_employee_id: 22,
      },
      15,
    )
    expect(apiPut).toHaveBeenCalledWith(
      '/api/v1/transport-season-vehicles/4/default-crew',
      {
        default_driver_employee_id: 11,
        default_external_driver_id: null,
        default_supervisor_employee_id: 22,
      },
      { headers: { 'X-Season-Id': '15' } },
    )
  })
})
