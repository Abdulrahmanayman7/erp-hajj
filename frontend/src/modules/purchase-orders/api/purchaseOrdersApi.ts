import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreatePurchaseOrderPayload,
  PurchaseOrder,
  UpdatePurchaseOrderPayload,
} from '../types/purchaseOrder'

const base = '/api/v1/purchase-orders'

function toQuery(filters: Record<string, string | number | undefined> = {}): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  })
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function listPurchaseOrders(params: {
  status?: string
  purchase_request_id?: number
  rfq_id?: number
  q?: string
  page?: number
} = {}): Promise<{ data: PurchaseOrder[]; meta: Record<string, number> }> {
  const response = await apiGet<PurchaseOrder[]>(`${base}${toQuery(params)}`)
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function getPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const response = await apiGet<PurchaseOrder>(`${base}/${id}`)
  return response.data
}

export async function createPurchaseOrder(payload: CreatePurchaseOrderPayload): Promise<PurchaseOrder> {
  const response = await apiPost<PurchaseOrder>(base, payload)
  return response.data
}

export async function updatePurchaseOrder(
  id: number,
  payload: UpdatePurchaseOrderPayload,
): Promise<PurchaseOrder> {
  const response = await apiPatch<PurchaseOrder>(`${base}/${id}`, payload)
  return response.data
}

export async function issuePurchaseOrder(id: number): Promise<PurchaseOrder> {
  const response = await apiPost<PurchaseOrder>(`${base}/${id}/issue`)
  return response.data
}

export async function cancelPurchaseOrder(id: number, reason: string): Promise<PurchaseOrder> {
  const response = await apiPost<PurchaseOrder>(`${base}/${id}/cancel`, { reason })
  return response.data
}

export async function closePurchaseOrder(
  id: number,
  closeReason?: string | null,
): Promise<PurchaseOrder> {
  const payload =
    closeReason != null && closeReason.trim() !== ''
      ? { close_reason: closeReason.trim() }
      : {}
  const response = await apiPost<PurchaseOrder>(`${base}/${id}/close`, payload)
  return response.data
}
