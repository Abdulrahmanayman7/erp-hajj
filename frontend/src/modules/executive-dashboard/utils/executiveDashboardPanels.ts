import type { OperationalKpiCard } from '@/modules/operational-kpis/types/operationalKpis'

/** S10-A3 panel KPI ids — reused from S09; no derived occupancy % or planned progress. */
export const ACCOMMODATION_PANEL_IDS = ['A02', 'A03'] as const
export const PROJECT_PANEL_IDS = ['J07', 'J09'] as const

export type AccommodationPanelId = (typeof ACCOMMODATION_PANEL_IDS)[number]
export type ProjectPanelId = (typeof PROJECT_PANEL_IDS)[number]

export function pickKpisById(
  kpis: OperationalKpiCard[],
  ids: readonly string[],
): OperationalKpiCard[] {
  const byId = new Map(kpis.map((kpi) => [kpi.id, kpi]))
  return ids.map((id) => byId.get(id)).filter((kpi): kpi is OperationalKpiCard => kpi != null)
}

/** Response must never invent occupancy % or planned-progress lag fields (U01=C, U02=C). */
export function assertNoUnsupportedPanelMetrics(payload: unknown): string[] {
  const forbidden = [
    'occupancy_percent',
    'occupancy_percentage',
    'planned_progress',
    'progress_lag',
    'delay_percent',
    'delay_percentage',
  ]
  const encoded = JSON.stringify(payload ?? {})
  return forbidden.filter((key) => encoded.includes(`"${key}"`))
}
