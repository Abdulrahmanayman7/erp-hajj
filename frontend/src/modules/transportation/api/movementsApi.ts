import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateRunFormState,
  ListMovementsParams,
  MovementFormState,
  PaginationMeta,
  TransportMovement,
  TransportVehicleRun,
} from '../types/operations'
import { seasonRequestOptions } from './seasonRequest'

function toQuery(params: Omit<ListMovementsParams, 'seasonId'>): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

function movementBody(payload: MovementFormState) {
  return {
    origin_point_id: Number(payload.origin_point_id),
    destination_point_id: Number(payload.destination_point_id),
    planned_departure_at: payload.planned_departure_at,
    planned_arrival_at: payload.planned_arrival_at,
    notes: payload.notes.trim() || null,
  }
}

export async function listMovements(
  params: ListMovementsParams = {},
): Promise<{ data: TransportMovement[]; meta: PaginationMeta }> {
  const { seasonId, ...rest } = params
  const response = await apiGet<TransportMovement[]>(
    `/api/v1/transport-movements${toQuery(rest)}`,
    seasonRequestOptions(seasonId),
  )
  return { data: response.data, meta: response.meta as unknown as PaginationMeta }
}

export async function getMovement(
  id: number,
  seasonId?: number | null,
): Promise<TransportMovement> {
  const response = await apiGet<TransportMovement>(
    `/api/v1/transport-movements/${id}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function createMovement(
  payload: MovementFormState,
  seasonId?: number | null,
): Promise<TransportMovement> {
  const response = await apiPost<TransportMovement>(
    '/api/v1/transport-movements',
    movementBody(payload),
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function updateMovement(
  id: number,
  payload: MovementFormState,
  seasonId?: number | null,
): Promise<TransportMovement> {
  const response = await apiPatch<TransportMovement>(
    `/api/v1/transport-movements/${id}`,
    movementBody(payload),
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function planMovement(id: number, seasonId?: number | null): Promise<TransportMovement> {
  const response = await apiPost<TransportMovement>(
    `/api/v1/transport-movements/${id}/plan`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function startMovement(id: number, seasonId?: number | null): Promise<TransportMovement> {
  const response = await apiPost<TransportMovement>(
    `/api/v1/transport-movements/${id}/start`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function completeMovement(
  id: number,
  seasonId?: number | null,
): Promise<TransportMovement> {
  const response = await apiPost<TransportMovement>(
    `/api/v1/transport-movements/${id}/complete`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function cancelMovement(
  id: number,
  reason: string,
  seasonId?: number | null,
): Promise<TransportMovement> {
  const response = await apiPost<TransportMovement>(
    `/api/v1/transport-movements/${id}/cancel`,
    { reason },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function createRun(
  movementId: number,
  payload: CreateRunFormState,
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const body: Record<string, unknown> = {
    transport_season_vehicle_id: Number(payload.transport_season_vehicle_id),
    notes: payload.notes.trim() || null,
  }
  const override = String(payload.capacity_override ?? '').trim()
  if (override !== '') body.capacity_override = Number(override)

  const response = await apiPost<TransportVehicleRun>(
    `/api/v1/transport-movements/${movementId}/runs`,
    body,
    seasonRequestOptions(seasonId),
  )
  return response.data
}
