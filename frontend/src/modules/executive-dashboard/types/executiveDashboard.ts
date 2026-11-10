import type { OperationalKpiCard, OperationalKpiSeason } from '@/modules/operational-kpis/types/operationalKpis'

export type ExecutiveDashboardResponseState = 'ok' | 'no_domain'

export type GpsFreshnessClass = 'real' | 'stale' | 'unavailable'

export interface ExecutiveDashboardGpsMarker {
  transport_season_vehicle_id: number
  lat: number
  lng: number
  freshness_class: GpsFreshnessClass
  recorded_at: string
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
