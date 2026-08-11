import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type { InventoryBalance } from '../types/balances'
import type {
  InventoryItem,
  InventoryItemPayload,
  InventoryItemsListMeta,
  ListInventoryItemsParams,
} from '../types/items'

function toQuery(params: ListInventoryItemsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    if (typeof value === 'boolean') {
      query.set(key, value ? '1' : '0')
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listInventoryItems(
  params: ListInventoryItemsParams = {},
): Promise<{ data: InventoryItem[]; meta: InventoryItemsListMeta }> {
  const response = await apiGet<InventoryItem[]>(`/api/v1/inventory-items${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as InventoryItemsListMeta,
  }
}

export async function getInventoryItem(id: number): Promise<InventoryItem> {
  const response = await apiGet<InventoryItem>(`/api/v1/inventory-items/${id}`)
  return response.data
}

export async function createInventoryItem(payload: InventoryItemPayload): Promise<InventoryItem> {
  const response = await apiPost<InventoryItem>('/api/v1/inventory-items', payload)
  return response.data
}

export async function updateInventoryItem(
  id: number,
  payload: InventoryItemPayload,
): Promise<InventoryItem> {
  const response = await apiPatch<InventoryItem>(`/api/v1/inventory-items/${id}`, payload)
  return response.data
}

export async function activateInventoryItem(id: number): Promise<InventoryItem> {
  const response = await apiPost<InventoryItem>(`/api/v1/inventory-items/${id}/activate`)
  return response.data
}

export async function deactivateInventoryItem(id: number): Promise<InventoryItem> {
  const response = await apiPost<InventoryItem>(`/api/v1/inventory-items/${id}/deactivate`)
  return response.data
}

export async function deleteInventoryItem(id: number): Promise<void> {
  await apiDelete(`/api/v1/inventory-items/${id}`)
}

export async function listItemBalances(itemId: number): Promise<InventoryBalance[]> {
  const response = await apiGet<InventoryBalance[]>(`/api/v1/inventory-items/${itemId}/balances`)
  return response.data
}
