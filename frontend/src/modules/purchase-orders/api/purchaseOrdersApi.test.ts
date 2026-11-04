import { beforeEach, describe, expect, it, vi } from 'vitest'

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import * as purchaseOrdersApi from './purchaseOrdersApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

describe('purchaseOrdersApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lists purchase orders with filters', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      data: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    })

    await purchaseOrdersApi.listPurchaseOrders({ status: 'draft', rfq_id: 9, q: 'PO' })

    expect(apiGet).toHaveBeenCalledWith('/api/v1/purchase-orders?status=draft&rfq_id=9&q=PO')
  })

  it('creates issues updates and cancels purchase orders', async () => {
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 1 } })
    vi.mocked(apiPatch).mockResolvedValue({ data: { id: 1 } })
    vi.mocked(apiGet).mockResolvedValue({ data: { id: 1 } })

    await purchaseOrdersApi.createPurchaseOrder({ rfq_id: 3 })
    await purchaseOrdersApi.getPurchaseOrder(1)
    await purchaseOrdersApi.updatePurchaseOrder(1, { notes: 'ملاحظات' })
    await purchaseOrdersApi.issuePurchaseOrder(1)
    await purchaseOrdersApi.cancelPurchaseOrder(1, 'سبب الإلغاء الكافي')
    await purchaseOrdersApi.closePurchaseOrder(1, 'إقفال مع إلغاء المتبقي بعد استلام جزئي')

    expect(apiPost).toHaveBeenCalledWith('/api/v1/purchase-orders', { rfq_id: 3 })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/purchase-orders/1')
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/purchase-orders/1', { notes: 'ملاحظات' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/purchase-orders/1/issue')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/purchase-orders/1/cancel', {
      reason: 'سبب الإلغاء الكافي',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/purchase-orders/1/close', {
      close_reason: 'إقفال مع إلغاء المتبقي بعد استلام جزئي',
    })
  })
})
