export type OperationalKpiState = 'ok' | 'undefined' | 'error'

export type OperationalKpiDomain =
  | 'transport'
  | 'accommodation'
  | 'purchase_requests'
  | 'rfq'
  | 'purchase_orders'
  | 'season_projects'
  | 'risks'
  | 'complaints'

export interface OperationalKpiCard {
  id: string
  domain: string
  label: string
  value: number | null
  display_value: string | null
  state: OperationalKpiState
  href: string | null
}

export interface OperationalKpiSeason {
  id: number
  name: string
  status: 'draft' | 'active' | 'closed' | string
  status_label: string
}

export type OperationalKpisResponseState = 'ok' | 'no_domain'

export interface OperationalKpisData {
  kpis: OperationalKpiCard[]
  season: OperationalKpiSeason
  response_state: OperationalKpisResponseState
}

export interface OperationalKpisMeta {
  generated_at?: string
  timezone?: string
  season_id?: number
}

export const DOMAIN_GROUP_ORDER = [
  'transport',
  'accommodation',
  'procurement',
  'season_projects',
  'risks_complaints',
] as const

export type DomainGroupKey = (typeof DOMAIN_GROUP_ORDER)[number]

export function domainToGroup(domain: string): DomainGroupKey {
  switch (domain) {
    case 'transport':
      return 'transport'
    case 'accommodation':
      return 'accommodation'
    case 'purchase_requests':
    case 'rfq':
    case 'purchase_orders':
      return 'procurement'
    case 'season_projects':
      return 'season_projects'
    case 'risks':
    case 'complaints':
      return 'risks_complaints'
    default:
      return 'procurement'
  }
}
