import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  ListVehiclesParams,
  PaginationMeta,
  TransportVehicle,
} from '../types/fleet'

function toQuery(params: ListVehiclesParams): string {
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

export async function listTransportVehicles(params: ListVehiclesParams = {}): Promise<{
  data: TransportVehicle[]
  meta: PaginationMeta
}> {
  const response = await apiGet<TransportVehicle[]>(`/api/v1/transport-vehicles${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}

export async function getTransportVehicle(id: number): Promise<TransportVehicle> {
  const response = await apiGet<TransportVehicle>(`/api/v1/transport-vehicles/${id}`)
  return response.data
}

export async function createTransportVehicle(payload: {
  plate_number: string
  vehicle_class: 'bus' | 'minibus' | 'car'
  notes: string
}): Promise<TransportVehicle> {
  const response = await apiPost<TransportVehicle>('/api/v1/transport-vehicles', {
    plate_number: payload.plate_number.trim(),
    vehicle_class: payload.vehicle_class,
    notes: payload.notes.trim() || null,
  })
  return response.data
}

export async function updateTransportVehicle(
  id: number,
  payload: Partial<{ plate_number: string; vehicle_class: string; notes: string | null }>,
): Promise<TransportVehicle> {
  const response = await apiPatch<TransportVehicle>(`/api/v1/transport-vehicles/${id}`, payload)
  return response.data
}

export async function deleteTransportVehicle(id: number): Promise<void> {
  await apiDelete(`/api/v1/transport-vehicles/${id}`)
}

export async function activateTransportVehicle(id: number): Promise<TransportVehicle> {
  const response = await apiPost<TransportVehicle>(`/api/v1/transport-vehicles/${id}/activate`)
  return response.data
}

export async function deactivateTransportVehicle(id: number): Promise<TransportVehicle> {
  const response = await apiPost<TransportVehicle>(`/api/v1/transport-vehicles/${id}/deactivate`)
  return response.data
}

export async function updateTransportVehicleLinks(
  id: number,
  payload: { asset_id: number | null; contract_id: number | null },
): Promise<TransportVehicle> {
  const response = await apiPut<TransportVehicle>(`/api/v1/transport-vehicles/${id}/links`, payload)
  return response.data
}
