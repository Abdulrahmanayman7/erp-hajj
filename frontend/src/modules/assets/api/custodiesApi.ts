import { apiGet } from '@/shared/api/http'

import type { AssetCustody, CustodiesListMeta, ListCustodiesParams } from '../types/custodies'

function toQuery(params: ListCustodiesParams): string {
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

export async function listAssetCustodies(
  params: ListCustodiesParams = {},
): Promise<{ data: AssetCustody[]; meta: CustodiesListMeta }> {
  const response = await apiGet<AssetCustody[]>(`/api/v1/asset-custodies${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as CustodiesListMeta,
  }
}

export async function getAssetCustody(id: number): Promise<AssetCustody> {
  const response = await apiGet<AssetCustody>(`/api/v1/asset-custodies/${id}`)
  return response.data
}

export async function listMyCustodies(
  params: ListCustodiesParams = {},
): Promise<{ data: AssetCustody[]; meta: CustodiesListMeta }> {
  const response = await apiGet<AssetCustody[]>(`/api/v1/my-custodies${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as CustodiesListMeta,
  }
}
