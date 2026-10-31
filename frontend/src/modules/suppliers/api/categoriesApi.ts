import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CategoriesListMeta,
  CreateCategoryPayload,
  ListCategoriesParams,
  SupplierCategory,
  UpdateCategoryPayload,
} from '../types/categories'

function toQuery(params: ListCategoriesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    if (typeof value === 'boolean') {
      query.set(key, value ? '1' : '0')
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listSupplierCategories(params: ListCategoriesParams = {}): Promise<{
  data: SupplierCategory[]
  meta: CategoriesListMeta
}> {
  const response = await apiGet<SupplierCategory[]>(
    `/api/v1/supplier-categories${toQuery(params)}`,
  )
  return {
    data: response.data,
    meta: response.meta as unknown as CategoriesListMeta,
  }
}

export async function createSupplierCategory(
  payload: CreateCategoryPayload,
): Promise<SupplierCategory> {
  const response = await apiPost<SupplierCategory>('/api/v1/supplier-categories', payload)
  return response.data
}

export async function updateSupplierCategory(
  id: number,
  payload: UpdateCategoryPayload,
): Promise<SupplierCategory> {
  const response = await apiPatch<SupplierCategory>(`/api/v1/supplier-categories/${id}`, payload)
  return response.data
}

export async function deactivateSupplierCategory(id: number): Promise<SupplierCategory> {
  const response = await apiPost<SupplierCategory>(
    `/api/v1/supplier-categories/${id}/deactivate`,
  )
  return response.data
}

export async function deleteSupplierCategory(id: number): Promise<void> {
  await apiDelete(`/api/v1/supplier-categories/${id}`)
}
