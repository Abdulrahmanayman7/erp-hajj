import { describe, expect, it } from 'vitest'

import { buildTenantNavGroups, filterNavGroups } from './useAppNavigation'

/**
 * S11-P1 frontend contract: Phase 2 cross-module nav wiring + dual gates.
 * Mocked unit coverage — does not prove browser E2E.
 */

const allowAll = (): boolean => true
const allowOnly = (...perms: string[]) => (permission: string): boolean => perms.includes(permission)

describe('S11 cross-module navigation contract', () => {
  it('exposes Phase 2 module routes used by integration flows A–I', () => {
    const groups = buildTenantNavGroups(allowAll)
    const byKey = Object.fromEntries(
      groups.flatMap((g) => g.items.map((item) => [item.key, item.to] as const)),
    )

    expect(byKey['pilgrims']).toBe('/app/pilgrims')
    expect(byKey['transport-movements']).toBe('/app/transport-movements')
    expect(byKey.accommodation).toBe('/app/accommodation')
    expect(byKey['operational-kpis']).toBe('/app/operational-kpis')
    expect(byKey['executive-dashboard']).toBe('/app/executive-dashboard')
    expect(byKey['purchase-requests']).toBe('/app/purchase-requests')
    expect(byKey['purchase-orders']).toBe('/app/purchase-orders')
    expect(byKey['goods-receipts']).toBe('/app/goods-receipts')
    expect(byKey['season-projects']).toBe('/app/season-projects')
    expect(byKey.risks).toBe('/app/risks')
    expect(byKey.complaints).toBe('/app/complaints')
    expect(byKey['season-templates']).toBe('/app/season-templates')
    expect(byKey['season-safe-copy']).toBe('/app/season-safe-copy')
  })

  it('keeps executive dashboard dual-gated and omits it when only one page perm is present', () => {
    const onlyDash = filterNavGroups(buildTenantNavGroups(allowOnly('executive_dashboard.view')), new Set(['executive_dashboard.view']))
    const onlyKpi = filterNavGroups(buildTenantNavGroups(allowOnly('operational_kpis.view')), new Set(['operational_kpis.view']))
    const both = filterNavGroups(
      buildTenantNavGroups(allowOnly('executive_dashboard.view', 'operational_kpis.view')),
      new Set(['executive_dashboard.view', 'operational_kpis.view']),
    )

    const keys = (g: ReturnType<typeof buildTenantNavGroups>) => g.flatMap((x) => x.items.map((i) => i.key))

    expect(keys(onlyDash)).not.toContain('executive-dashboard')
    expect(keys(onlyKpi)).toContain('operational-kpis')
    expect(keys(onlyKpi)).not.toContain('executive-dashboard')
    expect(keys(both)).toEqual(expect.arrayContaining(['operational-kpis', 'executive-dashboard']))
  })

  it('hides governance and procurement Phase 2 items without their view permissions', () => {
    const empty = filterNavGroups(buildTenantNavGroups(allowOnly()), new Set())
    const keys = empty.flatMap((g) => g.items.map((i) => i.key))

    expect(keys).not.toContain('season-projects')
    expect(keys).not.toContain('risks')
    expect(keys).not.toContain('complaints')
    expect(keys).not.toContain('rfqs')
    expect(keys).not.toContain('season-templates')
  })
})
