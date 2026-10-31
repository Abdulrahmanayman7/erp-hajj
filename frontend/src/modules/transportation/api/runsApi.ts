import { apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  PaginationMeta,
  PassengerStatus,
  TransportRunPassenger,
  TransportVehicleRun,
} from '../types/operations'
import { seasonRequestOptions } from './seasonRequest'

export async function getRun(id: number, seasonId?: number | null): Promise<TransportVehicleRun> {
  const response = await apiGet<TransportVehicleRun>(
    `/api/v1/transport-runs/${id}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function updateRun(
  id: number,
  payload: { capacity_override?: number | null; notes?: string | null },
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const response = await apiPatch<TransportVehicleRun>(
    `/api/v1/transport-runs/${id}`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function transitionRun(
  id: number,
  action: 'start-boarding' | 'depart' | 'arrive' | 'complete',
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const response = await apiPost<TransportVehicleRun>(
    `/api/v1/transport-runs/${id}/${action}`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function cancelRun(
  id: number,
  reason: string,
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const response = await apiPost<TransportVehicleRun>(
    `/api/v1/transport-runs/${id}/cancel`,
    { reason },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function assignRunVehicle(
  id: number,
  payload: { transport_season_vehicle_id: number; reason?: string | null },
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const response = await apiPut<TransportVehicleRun>(
    `/api/v1/transport-runs/${id}/vehicle`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function assignRunDriver(
  id: number,
  payload: {
    driver_employee_id?: number | null
    external_driver_id?: number | null
    reason?: string | null
  },
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const response = await apiPut<TransportVehicleRun>(
    `/api/v1/transport-runs/${id}/driver`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function listRunPassengers(
  runId: number,
  params: { status?: PassengerStatus | ''; page?: number; per_page?: number } = {},
  seasonId?: number | null,
): Promise<{ data: TransportRunPassenger[]; meta: PaginationMeta }> {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  const response = await apiGet<TransportRunPassenger[]>(
    `/api/v1/transport-runs/${runId}/passengers${qs ? `?${qs}` : ''}`,
    seasonRequestOptions(seasonId),
  )
  return { data: response.data, meta: response.meta as unknown as PaginationMeta }
}

export async function addRunPassengers(
  runId: number,
  payload: {
    source: 'individual' | 'pilgrim_group' | 'family_group'
    season_pilgrim_ids?: number[]
    source_id?: number
    entitlement_override?: boolean
    override_reason?: string
  },
  seasonId?: number | null,
): Promise<TransportVehicleRun> {
  const response = await apiPost<TransportVehicleRun>(
    `/api/v1/transport-runs/${runId}/passengers`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function cancelPassenger(
  passengerId: number,
  reason: string,
  seasonId?: number | null,
): Promise<TransportRunPassenger> {
  const response = await apiPost<TransportRunPassenger>(
    `/api/v1/transport-run-passengers/${passengerId}/cancel`,
    { reason },
    seasonRequestOptions(seasonId),
  )
  return response.data
}
