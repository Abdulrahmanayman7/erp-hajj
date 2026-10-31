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

describe('pointsApi', () => {
  beforeEach(() => {
    vi.resetModules()
    apiGet.mockReset()
    apiPost.mockReset()
    apiPatch.mockReset()
    apiDelete.mockReset()
  })

  it('lists points with filters', async () => {
    apiGet.mockResolvedValue({
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })
    const { listTransportPoints } = await import('./pointsApi')
    await listTransportPoints({ search: 'JED', is_active: true, point_type: 'airport' })
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/transport-points?search=JED&is_active=1&point_type=airport',
    )
  })

  it('creates and updates a point', async () => {
    apiPost.mockResolvedValue({ data: { id: 1 } })
    apiPatch.mockResolvedValue({ data: { id: 1 } })
    const { createTransportPoint, updateTransportPoint } = await import('./pointsApi')
    await createTransportPoint({
      code: ' JED ',
      name: ' مطار جدة ',
      point_type: 'airport',
      notes: '',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/transport-points', {
      code: 'JED',
      name: 'مطار جدة',
      point_type: 'airport',
      notes: null,
    })
    await updateTransportPoint(1, { name: 'جدة' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/transport-points/1', { name: 'جدة' })
  })

  it('deletes a point', async () => {
    apiDelete.mockResolvedValue({ data: null })
    const { deleteTransportPoint } = await import('./pointsApi')
    await deleteTransportPoint(3)
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/transport-points/3')
  })
})
