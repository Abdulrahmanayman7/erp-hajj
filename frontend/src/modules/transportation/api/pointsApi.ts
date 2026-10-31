import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type { PaginationMeta } from '../types/fleet'
import type { ListPointsParams, PointFormState, TransportPoint } from '../types/points'

function toQuery(params: ListPointsParams): string {
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

export async function listTransportPoints(params: ListPointsParams = {}): Promise<{
  data: TransportPoint[]
  meta: PaginationMeta
}> {
  const response = await apiGet<TransportPoint[]>(`/api/v1/transport-points${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}

export async function createTransportPoint(payload: PointFormState): Promise<TransportPoint> {
  const response = await apiPost<TransportPoint>('/api/v1/transport-points', {
    code: payload.code.trim(),
    name: payload.name.trim(),
    point_type: payload.point_type,
    notes: payload.notes.trim() || null,
  })
  return response.data
}

export async function updateTransportPoint(
  id: number,
  payload: Partial<{
    code: string
    name: string
    point_type: string
    notes: string | null
  }>,
): Promise<TransportPoint> {
  const response = await apiPatch<TransportPoint>(`/api/v1/transport-points/${id}`, payload)
  return response.data
}

export async function deleteTransportPoint(id: number): Promise<void> {
  await apiDelete(`/api/v1/transport-points/${id}`)
}

export async function activateTransportPoint(id: number): Promise<TransportPoint> {
  const response = await apiPost<TransportPoint>(`/api/v1/transport-points/${id}/activate`)
  return response.data
}

export async function deactivateTransportPoint(id: number): Promise<TransportPoint> {
  const response = await apiPost<TransportPoint>(`/api/v1/transport-points/${id}/deactivate`)
  return response.data
}
