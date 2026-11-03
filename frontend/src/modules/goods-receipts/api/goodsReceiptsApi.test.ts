import { beforeEach, describe, expect, it, vi } from 'vitest'

import { apiGet, apiPost } from '@/shared/api/http'

import * as goodsReceiptsApi from './goodsReceiptsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}))

describe('goodsReceiptsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lists and creates and posts goods receipts', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      data: [],
      meta: { current_page: 1, per_page: 20, total: 0, last_page: 1 },
    })
    vi.mocked(apiPost).mockResolvedValue({ data: { id: 1 } })

    await goodsReceiptsApi.listGoodsReceipts({ status: 'draft', purchase_order_id: 4 })
    await goodsReceiptsApi.getGoodsReceipt(1)
    await goodsReceiptsApi.createGoodsReceipt({
      purchase_order_id: 4,
      warehouse_id: 2,
      lines: [{ purchase_order_line_id: 9, quantity: 1 }],
    })
    await goodsReceiptsApi.postGoodsReceipt(1)

    expect(apiGet).toHaveBeenCalledWith('/api/v1/goods-receipts?status=draft&purchase_order_id=4')
    expect(apiGet).toHaveBeenCalledWith('/api/v1/goods-receipts/1')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/goods-receipts', {
      purchase_order_id: 4,
      warehouse_id: 2,
      lines: [{ purchase_order_line_id: 9, quantity: 1 }],
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/goods-receipts/1/post')
  })
})
