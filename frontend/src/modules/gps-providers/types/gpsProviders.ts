export type GpsConnectionMethod =
  | 'rest_pull'
  | 'webhook'
  | 'traccar'
  | 'mobile_driver'
  | 'simulator'

export type GpsProviderHealthStatus =
  | 'unknown'
  | 'healthy'
  | 'degraded'
  | 'failed'
  | 'blocked'
  | 'disabled'

export type GpsFreshnessClass = 'real' | 'stale' | 'unavailable'

export type GpsMarkerStatus =
  | 'online'
  | 'offline'
  | 'stale'
  | 'no_device'
  | 'provider_unavailable'
  | 'simulated'

export interface GpsProvider {
  id: number
  name: string
  code: string
  connection_method: GpsConnectionMethod
  is_enabled: boolean
  settings: Record<string, unknown>
  has_credentials: boolean
  credentials_masked: string | null
  poll_interval_seconds: number | null
  last_sync_at: string | null
  last_health_status: GpsProviderHealthStatus | null
  last_error_sanitized: string | null
  created_at: string | null
  updated_at: string | null
}

export interface GpsDeviceLastKnown {
  latitude: number
  longitude: number
  recorded_at: string | null
  freshness_class: GpsFreshnessClass
  is_simulated: boolean
  speed_kph: number | null
  heading_deg: number | null
  source: string | null
}

export interface GpsDeviceBinding {
  id: number
  device_id: string
  external_device_id: string | null
  transport_season_vehicle_id: number
  gps_provider_id: number | null
  provider_code?: string | null
  provider_name?: string | null
  provider_health?: GpsProviderHealthStatus | null
  vehicle_number?: string | null
  plate_number?: string | null
  vehicle_class?: string | null
  is_active: boolean
  last_known?: GpsDeviceLastKnown | null
  created_at: string | null
  updated_at: string | null
}

export interface GpsProviderUpsertPayload {
  name: string
  code?: string
  connection_method: GpsConnectionMethod
  is_enabled?: boolean
  poll_interval_seconds?: number | null
  settings?: Record<string, unknown>
  credentials?: Record<string, string>
}

export interface GpsFleetMapMarker {
  transport_season_vehicle_id: number
  lat: number | null
  lng: number | null
  freshness_class: GpsFreshnessClass
  marker_status?: GpsMarkerStatus
  recorded_at: string | null
  speed_kph?: number | null
  heading_deg?: number | null
  provider_code?: string | null
  source?: string | null
  is_simulated?: boolean
  vehicle_label?: string | null
  vehicle_number?: string | null
  plate_number?: string | null
  vehicle_class?: string | null
  device_id?: string | null
}

export interface GpsFleetMapPanel {
  freshness_class: GpsFreshnessClass
  markers: GpsFleetMapMarker[]
}
