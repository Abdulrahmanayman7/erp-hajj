import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import * as warehousesApi from './warehousesApi'
import * as stockApi from './stockApi'
import * as balancesApi from './balancesApi'

describe('inventory api clients', () => {
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
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await warehousesApi.listWarehouses({
      search: 'رئيسي',
      is_active: true,
      organization_unit_id: 4,
    })

    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/warehouses?search=%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A&is_active=1&organization_unit_id=4',
    )
  })

  it('lists balances with stock_state filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await balancesApi.listInventoryBalances({
      warehouse_id: 2,
      stock_state: 'low',
      search: 'ماء',
    })

    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/inventory/balances?warehouse_id=2&stock_state=low&search=%D9%85%D8%A7%D8%A1',
    )
  })

  it('posts stock receive / transfer / adjust payloads to explicit endpoints', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1 },
    })

    await stockApi.receiveStock({
      warehouse_id: 1,
      inventory_item_id: 2,
      quantity: '10',
      reason: 'استلام',
      as_opening: true,
    })
    await stockApi.transferStock({
      source_warehouse_id: 1,
      destination_warehouse_id: 2,
      inventory_item_id: 3,
      quantity: '5',
      reason: 'تحويل',
    })
    await stockApi.adjustStock({
      warehouse_id: 1,
      inventory_item_id: 2,
      direction: 'out',
      quantity: '1',
      reason: 'جرد',
    })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/inventory/receipts', {
      warehouse_id: 1,
      inventory_item_id: 2,
      quantity: '10',
      reason: 'استلام',
      as_opening: true,
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/inventory/transfers', {
      source_warehouse_id: 1,
      destination_warehouse_id: 2,
      inventory_item_id: 3,
      quantity: '5',
      reason: 'تحويل',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/inventory/adjustments', {
      warehouse_id: 1,
      inventory_item_id: 2,
      direction: 'out',
      quantity: '1',
      reason: 'جرد',
    })
  })

  it('activates and deletes warehouses via path endpoints', async () => {
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: '', data: { id: 9 } })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })
    vi.mocked(apiPatch).mockResolvedValue({ success: true, message: '', data: { id: 9 } })

    await warehousesApi.activateWarehouse(9)
    await warehousesApi.updateWarehouse(9, { name: 'محدث' })
    await warehousesApi.deleteWarehouse(9)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/warehouses/9/activate')
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/warehouses/9', { name: 'محدث' })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/warehouses/9')
  })
})
