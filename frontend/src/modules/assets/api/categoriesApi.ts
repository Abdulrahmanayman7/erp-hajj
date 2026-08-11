import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  AssetCategory,
  AssetCategoryPayload,
  ListAssetCategoriesParams,
  UpdateAssetCategoryPayload,
} from '../types/categories'

function toQuery(params: ListAssetCategoriesParams): string {
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

export async function listAssetCategories(
  params: ListAssetCategoriesParams = {},
): Promise<AssetCategory[]> {
  const response = await apiGet<AssetCategory[]>(`/api/v1/asset-categories${toQuery(params)}`)
  return response.data
}

export async function createAssetCategory(
  payload: AssetCategoryPayload,
): Promise<AssetCategory> {
  const response = await apiPost<AssetCategory>('/api/v1/asset-categories', payload)
  return response.data
}

export async function updateAssetCategory(
  id: number,
  payload: UpdateAssetCategoryPayload,
): Promise<AssetCategory> {
  const response = await apiPatch<AssetCategory>(`/api/v1/asset-categories/${id}`, payload)
  return response.data
}

export async function deleteAssetCategory(id: number): Promise<void> {
  await apiDelete(`/api/v1/asset-categories/${id}`)
}
