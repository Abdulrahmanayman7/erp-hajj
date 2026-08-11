import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  InventoryCategory,
  InventoryCategoryPayload,
  ListInventoryCategoriesParams,
  UpdateInventoryCategoryPayload,
} from '../types/categories'

function toQuery(params: ListInventoryCategoriesParams): string {
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

export async function listInventoryCategories(
  params: ListInventoryCategoriesParams = {},
): Promise<InventoryCategory[]> {
  const response = await apiGet<InventoryCategory[]>(
    `/api/v1/inventory-categories${toQuery(params)}`,
  )
  return response.data
}

export async function createInventoryCategory(
  payload: InventoryCategoryPayload,
): Promise<InventoryCategory> {
  const response = await apiPost<InventoryCategory>('/api/v1/inventory-categories', payload)
  return response.data
}

export async function updateInventoryCategory(
  id: number,
  payload: UpdateInventoryCategoryPayload,
): Promise<InventoryCategory> {
  const response = await apiPatch<InventoryCategory>(
    `/api/v1/inventory-categories/${id}`,
    payload,
  )
  return response.data
}

export async function deleteInventoryCategory(id: number): Promise<void> {
  await apiDelete(`/api/v1/inventory-categories/${id}`)
}
