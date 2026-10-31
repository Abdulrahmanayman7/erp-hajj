import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreatePilgrimPayload,
  ListPilgrimsParams,
  Pilgrim,
  PilgrimsListMeta,
  UpdatePilgrimPayload,
} from '../types/pilgrims'

function toQuery(params: ListPilgrimsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listPilgrims(params: ListPilgrimsParams = {}): Promise<{
  data: Pilgrim[]
  meta: PilgrimsListMeta
}> {
  const response = await apiGet<Pilgrim[]>(`/api/v1/pilgrims${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as PilgrimsListMeta,
  }
}

export async function getPilgrim(id: number): Promise<Pilgrim> {
  const response = await apiGet<Pilgrim>(`/api/v1/pilgrims/${id}`)
  return response.data
}

export async function createPilgrim(payload: CreatePilgrimPayload): Promise<Pilgrim> {
  const response = await apiPost<Pilgrim>('/api/v1/pilgrims', payload)
  return response.data
}

export async function updatePilgrim(
  id: number,
  payload: UpdatePilgrimPayload,
): Promise<Pilgrim> {
  const response = await apiPatch<Pilgrim>(`/api/v1/pilgrims/${id}`, payload)
  return response.data
}
