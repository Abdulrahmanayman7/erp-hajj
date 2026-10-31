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

describe('vehiclesApi', () => {
  beforeEach(() => {
    vi.resetModules()
    apiGet.mockReset()
    apiPost.mockReset()
    apiPatch.mockReset()
    apiDelete.mockReset()
  })

  it('lists vehicles with boolean filters encoded as 1/0', async () => {
    apiGet.mockResolvedValue({
      data: [{ id: 1, plate_number: 'A-1', vehicle_class: 'bus', is_active: true }],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })
    const { listTransportVehicles } = await import('./vehiclesApi')
    const result = await listTransportVehicles({ search: 'A', is_active: false, page: 2 })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/transport-vehicles?search=A&is_active=0&page=2')
    expect(result.data).toHaveLength(1)
  })

  it('creates a vehicle with trimmed payload', async () => {
    apiPost.mockResolvedValue({
      data: { id: 9, plate_number: 'XYZ-1', vehicle_class: 'car', is_active: true },
    })
    const { createTransportVehicle } = await import('./vehiclesApi')
    await createTransportVehicle({
      plate_number: '  XYZ-1  ',
      vehicle_class: 'car',
      notes: '  ',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/transport-vehicles', {
      plate_number: 'XYZ-1',
      vehicle_class: 'car',
      notes: null,
    })
  })

  it('patches and deletes by id', async () => {
    apiPatch.mockResolvedValue({ data: { id: 3 } })
    apiDelete.mockResolvedValue({ data: null })
    const { updateTransportVehicle, deleteTransportVehicle } = await import('./vehiclesApi')
    await updateTransportVehicle(3, { plate_number: 'NEW' })
    await deleteTransportVehicle(3)
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/transport-vehicles/3', { plate_number: 'NEW' })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/transport-vehicles/3')
  })
})
