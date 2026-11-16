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

export interface GpsDeviceBinding {
  id: number
  device_id: string
  external_device_id: string | null
  transport_season_vehicle_id: number
  gps_provider_id: number | null
  is_active: boolean
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
