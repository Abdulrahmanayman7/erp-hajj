import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as warehousesApi from './warehousesApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

const sampleWarehouse = {
  id: 1,
  warehouse_number: 'WH-000001',
  name: 'المستودع الرئيسي',
  description: null,
  location: 'مكة',
  is_active: true,
  notes: null,
  organization_unit: null,
  responsible_employee: null,
  created_by: null,
  created_at: '2026-08-11T10:00:00+00:00',
  updated_at: '2026-08-11T10:00:00+00:00',
}

describe('warehousesApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists warehouses with filters as query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [sampleWarehouse],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await warehousesApi.listWarehouses({
      search: 'رئيسي',
      is_active: true,
      organization_unit_id: 2,
      responsible_employee_id: 3,
    })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.warehouse_number).toBe('WH-000001')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/warehouses?search=%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A&is_active=1&organization_unit_id=2&responsible_employee_id=3',
    )
  })

  it('gets a warehouse by id', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: sampleWarehouse,
    })

    const warehouse = await warehousesApi.getWarehouse(1)

    expect(warehouse.name).toBe('المستودع الرئيسي')
    expect(apiGet).toHaveBeenCalledWith('/api/v1/warehouses/1')
  })

  it('creates, updates, activates, deactivates and deletes', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: sampleWarehouse,
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { ...sampleWarehouse, name: 'محدث' },
    })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })

    await warehousesApi.createWarehouse({ name: 'المستودع الرئيسي', is_active: true })
    await warehousesApi.updateWarehouse(1, { name: 'محدث' })
    await warehousesApi.activateWarehouse(1)
    await warehousesApi.deactivateWarehouse(1)
    await warehousesApi.deleteWarehouse(1)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/warehouses', {
      name: 'المستودع الرئيسي',
      is_active: true,
    })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/warehouses/1', { name: 'محدث' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/warehouses/1/activate')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/warehouses/1/deactivate')
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/warehouses/1')
  })
})
