import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreatePositionPayload,
  ListPositionsParams,
  Position,
  PositionsListMeta,
  UpdatePositionPayload,
} from '../types/positions'

function toQuery(params: ListPositionsParams): string {
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

export async function listPositions(params: ListPositionsParams = {}): Promise<{
  data: Position[]
  meta: PositionsListMeta
}> {
  const response = await apiGet<Position[]>(`/api/v1/positions${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as PositionsListMeta,
  }
}

export async function createPosition(payload: CreatePositionPayload): Promise<Position> {
  const response = await apiPost<Position>('/api/v1/positions', payload)
  return response.data
}

export async function updatePosition(
  id: number,
  payload: UpdatePositionPayload,
): Promise<Position> {
  const response = await apiPatch<Position>(`/api/v1/positions/${id}`, payload)
  return response.data
}

export async function activatePosition(id: number): Promise<Position> {
  const response = await apiPost<Position>(`/api/v1/positions/${id}/activate`)
  return response.data
}

export async function deactivatePosition(id: number): Promise<Position> {
  const response = await apiPost<Position>(`/api/v1/positions/${id}/deactivate`)
  return response.data
}

export async function deletePosition(id: number): Promise<void> {
  await apiDelete(`/api/v1/positions/${id}`)
}
