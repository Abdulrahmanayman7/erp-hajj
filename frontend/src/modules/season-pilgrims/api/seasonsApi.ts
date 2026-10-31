import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateSeasonPayload,
  ListSeasonsParams,
  Season,
  SeasonsListMeta,
  UpdateSeasonPayload,
} from '../types/seasons'

function toQuery(params: ListSeasonsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '' || value === 'all') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listSeasons(params: ListSeasonsParams = {}): Promise<{
  data: Season[]
  meta: SeasonsListMeta
}> {
  const response = await apiGet<Season[]>(`/api/v1/seasons${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as SeasonsListMeta,
  }
}

export async function getSeason(id: number): Promise<Season> {
  const response = await apiGet<Season>(`/api/v1/seasons/${id}`)
  return response.data
}

export async function createSeason(payload: CreateSeasonPayload): Promise<Season> {
  const response = await apiPost<Season>('/api/v1/seasons', payload)
  return response.data
}

export async function updateSeason(id: number, payload: UpdateSeasonPayload): Promise<Season> {
  const response = await apiPatch<Season>(`/api/v1/seasons/${id}`, payload)
  return response.data
}

export async function activateSeason(id: number): Promise<Season> {
  const response = await apiPost<Season>(`/api/v1/seasons/${id}/activate`)
  return response.data
}

export async function closeSeason(id: number): Promise<Season> {
  const response = await apiPost<Season>(`/api/v1/seasons/${id}/close`)
  return response.data
}

export async function reopenSeason(id: number, reason: string): Promise<Season> {
  const response = await apiPost<Season>(`/api/v1/seasons/${id}/reopen`, { reason })
  return response.data
}
