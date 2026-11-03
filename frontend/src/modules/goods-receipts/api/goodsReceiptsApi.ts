import { apiGet, apiPost } from '@/shared/api/http'

import type { CreateGoodsReceiptPayload, GoodsReceipt } from '../types/goodsReceipt'

const base = '/api/v1/goods-receipts'

function toQuery(filters: Record<string, string | number | undefined> = {}): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  })
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function listGoodsReceipts(params: {
  status?: string
  purchase_order_id?: number
  q?: string
  page?: number
} = {}): Promise<{ data: GoodsReceipt[]; meta: Record<string, number> }> {
  const response = await apiGet<GoodsReceipt[]>(`${base}${toQuery(params)}`)
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function getGoodsReceipt(id: number): Promise<GoodsReceipt> {
  const response = await apiGet<GoodsReceipt>(`${base}/${id}`)
  return response.data
}

export async function createGoodsReceipt(payload: CreateGoodsReceiptPayload): Promise<GoodsReceipt> {
  const response = await apiPost<GoodsReceipt>(base, payload)
  return response.data
}

export async function postGoodsReceipt(id: number): Promise<GoodsReceipt> {
  const response = await apiPost<GoodsReceipt>(`${base}/${id}/post`)
  return response.data
}
