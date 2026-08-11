import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CategoriesListMeta,
  ContractCategory,
  CreateCategoryPayload,
  ListCategoriesParams,
  UpdateCategoryPayload,
} from '../types/categories'

function toQuery(params: ListCategoriesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listCategories(params: ListCategoriesParams = {}): Promise<{
  data: ContractCategory[]
  meta: CategoriesListMeta
}> {
  const response = await apiGet<ContractCategory[]>(
    `/api/v1/contract-categories${toQuery(params)}`,
  )
  return {
    data: response.data,
    meta: response.meta as unknown as CategoriesListMeta,
  }
}

export async function getCategory(id: number): Promise<ContractCategory> {
  const response = await apiGet<ContractCategory>(`/api/v1/contract-categories/${id}`)
  return response.data
}

export async function createCategory(payload: CreateCategoryPayload): Promise<ContractCategory> {
  const response = await apiPost<ContractCategory>('/api/v1/contract-categories', payload)
  return response.data
}

export async function updateCategory(
  id: number,
  payload: UpdateCategoryPayload,
): Promise<ContractCategory> {
  const response = await apiPatch<ContractCategory>(`/api/v1/contract-categories/${id}`, payload)
  return response.data
}

export async function activateCategory(id: number): Promise<ContractCategory> {
  const response = await apiPost<ContractCategory>(`/api/v1/contract-categories/${id}/activate`)
  return response.data
}

export async function deactivateCategory(id: number): Promise<ContractCategory> {
  const response = await apiPost<ContractCategory>(`/api/v1/contract-categories/${id}/deactivate`)
  return response.data
}

export async function deleteCategory(id: number): Promise<void> {
  await apiDelete(`/api/v1/contract-categories/${id}`)
}
