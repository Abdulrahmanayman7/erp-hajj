import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  ListWarehousesParams,
  Warehouse,
  WarehousePayload,
  WarehousesListMeta,
} from '../types/warehouses'

function toQuery(params: ListWarehousesParams): string {
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

export async function listWarehouses(
  params: ListWarehousesParams = {},
): Promise<{ data: Warehouse[]; meta: WarehousesListMeta }> {
  const response = await apiGet<Warehouse[]>(`/api/v1/warehouses${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as WarehousesListMeta,
  }
}

export async function getWarehouse(id: number): Promise<Warehouse> {
  const response = await apiGet<Warehouse>(`/api/v1/warehouses/${id}`)
  return response.data
}

export async function createWarehouse(payload: WarehousePayload): Promise<Warehouse> {
  const response = await apiPost<Warehouse>('/api/v1/warehouses', payload)
  return response.data
}

export async function updateWarehouse(id: number, payload: WarehousePayload): Promise<Warehouse> {
  const response = await apiPatch<Warehouse>(`/api/v1/warehouses/${id}`, payload)
  return response.data
}

export async function activateWarehouse(id: number): Promise<Warehouse> {
  const response = await apiPost<Warehouse>(`/api/v1/warehouses/${id}/activate`)
  return response.data
}

export async function deactivateWarehouse(id: number): Promise<Warehouse> {
  const response = await apiPost<Warehouse>(`/api/v1/warehouses/${id}/deactivate`)
  return response.data
}

export async function deleteWarehouse(id: number): Promise<void> {
  await apiDelete(`/api/v1/warehouses/${id}`)
}
