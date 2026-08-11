import { apiGet } from '@/shared/api/http'

import type {
  InventoryMovement,
  InventoryMovementsListMeta,
  ListInventoryMovementsParams,
} from '../types/movements'

function toQuery(params: ListInventoryMovementsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listInventoryMovements(
  params: ListInventoryMovementsParams = {},
): Promise<{ data: InventoryMovement[]; meta: InventoryMovementsListMeta }> {
  const response = await apiGet<InventoryMovement[]>(
    `/api/v1/inventory/movements${toQuery(params)}`,
  )
  return {
    data: response.data,
    meta: response.meta as unknown as InventoryMovementsListMeta,
  }
}

export async function getInventoryMovement(id: number): Promise<InventoryMovement> {
  const response = await apiGet<InventoryMovement>(`/api/v1/inventory/movements/${id}`)
  return response.data
}
