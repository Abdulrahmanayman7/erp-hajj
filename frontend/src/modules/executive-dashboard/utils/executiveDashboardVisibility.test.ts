import { describe, expect, it } from 'vitest'

import {
  canPickExecutiveSeasons,
  shouldShowExecutiveAlertsPanel,
  shouldShowExecutiveGpsPanel,
  shouldShowExecutiveReportsPanel,
} from './executiveDashboardVisibility'

describe('executiveDashboardVisibility', () => {
  it('gates season picker on seasons.view', () => {
    expect(canPickExecutiveSeasons((p) => p === 'seasons.view')).toBe(true)
    expect(canPickExecutiveSeasons(() => false)).toBe(false)
  })

  it('shows alerts panel only when API returned alerts summary', () => {
    expect(shouldShowExecutiveAlertsPanel(null)).toBe(false)
    expect(shouldShowExecutiveAlertsPanel(undefined)).toBe(false)
    expect(shouldShowExecutiveAlertsPanel({ open_count: 0 })).toBe(true)
  })

  it('shows reports only with executive_reports.view, confirmed season, and ready-like state', () => {
    const canReports = (p: string) => p === 'executive_reports.view'
    expect(shouldShowExecutiveReportsPanel(canReports, 9, 'ready')).toBe(true)
    expect(shouldShowExecutiveReportsPanel(canReports, 9, 'no_domain')).toBe(true)
    expect(shouldShowExecutiveReportsPanel(canReports, 9, 'empty')).toBe(true)
    expect(shouldShowExecutiveReportsPanel(canReports, null, 'ready')).toBe(false)
    expect(shouldShowExecutiveReportsPanel(canReports, 9, 'need_confirm')).toBe(false)
    expect(shouldShowExecutiveReportsPanel(() => false, 9, 'ready')).toBe(false)
  })

  it('shows GPS panel only when payload.gps is present (gps.map.view server gate)', () => {
    expect(shouldShowExecutiveGpsPanel(null)).toBe(false)
    expect(shouldShowExecutiveGpsPanel(undefined)).toBe(false)
    expect(shouldShowExecutiveGpsPanel({ freshness_class: 'real', markers: [] })).toBe(true)
  })
})
