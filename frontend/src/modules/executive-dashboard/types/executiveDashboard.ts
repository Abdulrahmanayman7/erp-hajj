import type { OperationalKpiCard, OperationalKpiSeason } from '@/modules/operational-kpis/types/operationalKpis'

export type ExecutiveDashboardResponseState = 'ok' | 'no_domain'

export type GpsFreshnessClass = 'real' | 'stale' | 'unavailable'

export type GpsMarkerStatus =
  | 'online'
  | 'offline'
  | 'stale'
  | 'no_device'
  | 'provider_unavailable'
  | 'simulated'

export interface ExecutiveDashboardGpsMarker {
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

export interface ExecutiveDashboardGps {
  freshness_class: GpsFreshnessClass
  markers: ExecutiveDashboardGpsMarker[]
}

export interface ExecutiveDashboardPanelBlock {
  kpis: OperationalKpiCard[]
}

export type ExecutiveAlertStatus = 'open' | 'acknowledged' | 'cleared'
export type ExecutiveAlertSeverity = 'warning' | 'critical'
/** Only rules with an approved formula exist (OCC / PROJ-LAG / COMP-SLA are deferred-blocked). */
export type ExecutiveAlertRuleCode = 'ALT-RISK-HIGH' | 'ALT-PROC-OVERDUE'

export interface ExecutiveAlert {
  id: number
  season_id: number
  rule_code: ExecutiveAlertRuleCode
  status: ExecutiveAlertStatus
  severity: ExecutiveAlertSeverity
  title: string
  body: string | null
  source_entity_type: string | null
  source_entity_id: number | null
  opened_at: string
  last_seen_at: string
  acknowledged_at: string | null
  acknowledged_by: number | null
  cleared_at: string | null
}

export interface ExecutiveAlertsPanelSummary {
  open_count: number
  acknowledged_count: number
  critical_count: number
  items: ExecutiveAlert[]
}

export interface ExecutiveDashboardPanels {
  season: OperationalKpiSeason
  accommodation?: ExecutiveDashboardPanelBlock
  projects?: ExecutiveDashboardPanelBlock
  alerts?: ExecutiveAlertsPanelSummary
}

export interface ExecutiveDashboardData {
  season: OperationalKpiSeason
  kpis: OperationalKpiCard[]
  response_state: ExecutiveDashboardResponseState
  panels: ExecutiveDashboardPanels
  gps?: ExecutiveDashboardGps
}

export interface ExecutiveDashboardMeta {
  generated_at: string
  timezone: string
  season_id: number
}
