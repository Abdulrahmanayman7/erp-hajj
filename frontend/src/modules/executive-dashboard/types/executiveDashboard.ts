import type { OperationalKpiCard, OperationalKpiSeason } from '@/modules/operational-kpis/types/operationalKpis'

export type ExecutiveDashboardResponseState = 'ok' | 'no_domain'

export interface ExecutiveDashboardGps {
  freshness_class: 'unavailable'
  markers: []
}

export interface ExecutiveDashboardData {
  season: OperationalKpiSeason
  kpis: OperationalKpiCard[]
  response_state: ExecutiveDashboardResponseState
  gps?: ExecutiveDashboardGps
}

export interface ExecutiveDashboardMeta {
  generated_at: string
  timezone: string
  season_id: number
}
