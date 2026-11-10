/**
 * Client-side panel visibility for the executive dashboard.
 * Server policies remain authoritative; these helpers only mirror UI gates.
 */

export type ExecutiveDashboardPageState =
  | 'loading_seasons'
  | 'seasons_error'
  | 'no_seasons'
  | 'need_confirm'
  | 'loading'
  | 'error'
  | 'no_domain'
  | 'empty'
  | 'ready'

export function canPickExecutiveSeasons(can: (permission: string) => boolean): boolean {
  return can('seasons.view')
}

export function shouldShowExecutiveAlertsPanel(alertsSummary: unknown): boolean {
  return alertsSummary != null
}

export function shouldShowExecutiveReportsPanel(
  can: (permission: string) => boolean,
  confirmedSeasonId: number | null,
  pageState: ExecutiveDashboardPageState,
): boolean {
  return (
    can('executive_reports.view') &&
    confirmedSeasonId != null &&
    (pageState === 'ready' || pageState === 'no_domain' || pageState === 'empty')
  )
}

export function shouldShowExecutiveGpsPanel(gps: unknown): boolean {
  return gps != null
}
