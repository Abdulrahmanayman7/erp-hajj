import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  Asset,
  AssetPayload,
  AssetsListMeta,
  AssignCustodyPayload,
  ListAssetsParams,
  ReturnCustodyPayload,
} from '../types/assets'

function toQuery(params: ListAssetsParams): string {
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

export async function listAssets(
  params: ListAssetsParams = {},
): Promise<{ data: Asset[]; meta: AssetsListMeta }> {
  const response = await apiGet<Asset[]>(`/api/v1/assets${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as AssetsListMeta,
  }
}

export async function getAsset(id: number): Promise<Asset> {
  const response = await apiGet<Asset>(`/api/v1/assets/${id}`)
  return response.data
}

export async function createAsset(payload: AssetPayload): Promise<Asset> {
  const response = await apiPost<Asset>('/api/v1/assets', payload)
  return response.data
}

export async function updateAsset(id: number, payload: AssetPayload): Promise<Asset> {
  const response = await apiPatch<Asset>(`/api/v1/assets/${id}`, payload)
  return response.data
}

export async function deleteAsset(id: number): Promise<void> {
  await apiDelete(`/api/v1/assets/${id}`)
}

export async function sendAssetToMaintenance(id: number): Promise<Asset> {
  const response = await apiPost<Asset>(`/api/v1/assets/${id}/maintenance`)
  return response.data
}

export async function restoreAsset(id: number): Promise<Asset> {
  const response = await apiPost<Asset>(`/api/v1/assets/${id}/restore`)
  return response.data
}

export async function retireAsset(id: number, reason: string): Promise<Asset> {
  const response = await apiPost<Asset>(`/api/v1/assets/${id}/retire`, { reason })
  return response.data
}

export async function declareAssetLost(id: number, reason: string): Promise<Asset> {
  const response = await apiPost<Asset>(`/api/v1/assets/${id}/declare-lost`, { reason })
  return response.data
}

export async function assignAssetCustody(
  id: number,
  payload: AssignCustodyPayload,
): Promise<Asset> {
  const response = await apiPost<Asset>(`/api/v1/assets/${id}/assign`, payload)
  return response.data
}

export async function returnAssetCustody(
  id: number,
  payload: ReturnCustodyPayload,
): Promise<Asset> {
  const response = await apiPost<Asset>(`/api/v1/assets/${id}/return`, payload)
  return response.data
}
