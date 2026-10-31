import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  DriverFormState,
  ListDriversParams,
  PaginationMeta,
  TransportExternalDriver,
} from '../types/fleet'

function toQuery(params: ListDriversParams): string {
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

export async function listTransportDrivers(params: ListDriversParams = {}): Promise<{
  data: TransportExternalDriver[]
  meta: PaginationMeta
}> {
  const response = await apiGet<TransportExternalDriver[]>(
    `/api/v1/transport-external-drivers${toQuery(params)}`,
  )
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}

export async function createTransportDriver(payload: DriverFormState): Promise<TransportExternalDriver> {
  const response = await apiPost<TransportExternalDriver>('/api/v1/transport-external-drivers', {
    full_name: payload.full_name.trim(),
    phone: payload.phone.trim() || null,
    notes: payload.notes.trim() || null,
  })
  return response.data
}

export async function updateTransportDriver(
  id: number,
  payload: Partial<{ full_name: string; phone: string | null; notes: string | null }>,
): Promise<TransportExternalDriver> {
  const response = await apiPatch<TransportExternalDriver>(
    `/api/v1/transport-external-drivers/${id}`,
    payload,
  )
  return response.data
}

export async function deleteTransportDriver(id: number): Promise<void> {
  await apiDelete(`/api/v1/transport-external-drivers/${id}`)
}

export async function activateTransportDriver(id: number): Promise<TransportExternalDriver> {
  const response = await apiPost<TransportExternalDriver>(
    `/api/v1/transport-external-drivers/${id}/activate`,
  )
  return response.data
}

export async function deactivateTransportDriver(id: number): Promise<TransportExternalDriver> {
  const response = await apiPost<TransportExternalDriver>(
    `/api/v1/transport-external-drivers/${id}/deactivate`,
  )
  return response.data
}
