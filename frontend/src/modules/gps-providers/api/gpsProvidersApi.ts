import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'
import type {
  GpsDeviceBinding,
  GpsFleetMapPanel,
  GpsProvider,
  GpsProviderUpsertPayload,
} from '../types/gpsProviders'

export async function listGpsProviders(): Promise<GpsProvider[]> {
  const response = await apiGet<GpsProvider[]>('/api/v1/gps/providers')
  return response.data
}

export async function createGpsProvider(payload: GpsProviderUpsertPayload): Promise<GpsProvider> {
  const response = await apiPost<GpsProvider>('/api/v1/gps/providers', payload)
  return response.data
}

export async function updateGpsProvider(
  id: number,
  payload: Partial<GpsProviderUpsertPayload>,
): Promise<GpsProvider> {
  const response = await apiPatch<GpsProvider>(`/api/v1/gps/providers/${id}`, payload)
  return response.data
}

export async function deleteGpsProvider(id: number): Promise<void> {
  await apiDelete(`/api/v1/gps/providers/${id}`)
}

export async function testGpsProvider(id: number): Promise<{
  status: string
  message: string
  live_contact_made: boolean
  provider: GpsProvider
}> {
  const response = await apiPost<{
    status: string
    message: string
    live_contact_made: boolean
    provider: GpsProvider
  }>(`/api/v1/gps/providers/${id}/test`)
  return response.data
}

export async function syncGpsProvider(id: number): Promise<{
  applied: number
  skipped: number
  health: string
  message: string
  provider: GpsProvider
}> {
  const response = await apiPost<{
    applied: number
    skipped: number
    health: string
    message: string
    provider: GpsProvider
  }>(`/api/v1/gps/providers/${id}/sync`)
  return response.data
}

export async function listGpsDeviceBindings(params?: {
  providerId?: number
  search?: string
  isActive?: boolean
}): Promise<GpsDeviceBinding[]> {
  const query = new URLSearchParams()
  if (params?.providerId != null) query.set('gps_provider_id', String(params.providerId))
  if (params?.search) query.set('search', params.search)
  if (params?.isActive != null) query.set('is_active', params.isActive ? '1' : '0')
  const suffix = query.toString() ? `?${query.toString()}` : ''
  const response = await apiGet<GpsDeviceBinding[]>(`/api/v1/gps/device-bindings${suffix}`)
  return response.data
}

export async function createGpsDeviceBinding(payload: {
  device_id: string
  external_device_id?: string | null
  transport_season_vehicle_id: number
  gps_provider_id?: number | null
  is_active?: boolean
}): Promise<GpsDeviceBinding> {
  const response = await apiPost<GpsDeviceBinding>('/api/v1/gps/device-bindings', payload)
  return response.data
}

export async function deleteGpsDeviceBinding(id: number): Promise<void> {
  await apiDelete(`/api/v1/gps/device-bindings/${id}`)
}

export async function fetchGpsFleetMap(seasonId: number): Promise<GpsFleetMapPanel> {
  const response = await apiGet<GpsFleetMapPanel>(
    `/api/v1/gps/fleet-map?season_id=${encodeURIComponent(String(seasonId))}`,
  )
  return response.data
}
