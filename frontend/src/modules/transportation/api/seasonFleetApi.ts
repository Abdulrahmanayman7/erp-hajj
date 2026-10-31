import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  ListSeasonVehiclesParams,
  PaginationMeta,
  SeasonVehicleFormState,
  TransportSeasonVehicle,
} from '../types/fleet'
import { seasonRequestOptions } from './seasonRequest'

function toQuery(params: Omit<ListSeasonVehiclesParams, 'seasonId'>): string {
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

export async function listSeasonVehicles(
  params: ListSeasonVehiclesParams = {},
): Promise<{ data: TransportSeasonVehicle[]; meta: PaginationMeta }> {
  const { seasonId, ...rest } = params
  const response = await apiGet<TransportSeasonVehicle[]>(
    `/api/v1/transport-season-vehicles${toQuery(rest)}`,
    seasonRequestOptions(seasonId),
  )
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}

export async function createSeasonVehicle(
  payload: SeasonVehicleFormState,
  seasonId?: number | null,
): Promise<TransportSeasonVehicle> {
  const response = await apiPost<TransportSeasonVehicle>(
    '/api/v1/transport-season-vehicles',
    {
      transport_vehicle_id: Number(payload.transport_vehicle_id),
      operational_capacity: Number(payload.operational_capacity),
      service_level: payload.service_level,
      notes: payload.notes.trim() || null,
    },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function updateSeasonVehicle(
  id: number,
  payload: Partial<{
    operational_capacity: number
    service_level: string
    notes: string | null
  }>,
  seasonId?: number | null,
): Promise<TransportSeasonVehicle> {
  const response = await apiPatch<TransportSeasonVehicle>(
    `/api/v1/transport-season-vehicles/${id}`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deleteSeasonVehicle(id: number, seasonId?: number | null): Promise<void> {
  await apiDelete(`/api/v1/transport-season-vehicles/${id}`, seasonRequestOptions(seasonId))
}

export async function activateSeasonVehicle(
  id: number,
  seasonId?: number | null,
): Promise<TransportSeasonVehicle> {
  const response = await apiPost<TransportSeasonVehicle>(
    `/api/v1/transport-season-vehicles/${id}/activate`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deactivateSeasonVehicle(
  id: number,
  seasonId?: number | null,
): Promise<TransportSeasonVehicle> {
  const response = await apiPost<TransportSeasonVehicle>(
    `/api/v1/transport-season-vehicles/${id}/deactivate`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function setSeasonVehicleDefaultCrew(
  id: number,
  payload: {
    default_driver_employee_id: number | null
    default_external_driver_id: number | null
    default_supervisor_employee_id: number | null
  },
  seasonId?: number | null,
): Promise<TransportSeasonVehicle> {
  const response = await apiPut<TransportSeasonVehicle>(
    `/api/v1/transport-season-vehicles/${id}/default-crew`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}
