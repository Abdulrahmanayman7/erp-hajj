import type { OperationalKpiCard, DomainGroupKey } from '../types/operationalKpis'
import { DOMAIN_GROUP_ORDER, domainToGroup } from '../types/operationalKpis'

/** Arabic disclaimer for allow-listed list routes that are not exact KPI breakdowns (M1). */
export const DRILLDOWN_DISCLAIMER_AR = 'قائمة عامة — قد لا تطابق فلتر المؤشر'

export function isSafeAppHref(href: string | null | undefined): href is string {
  if (!href || typeof href !== 'string') return false
  const trimmed = href.trim()
  if (!trimmed.startsWith('/app')) return false
  if (trimmed.startsWith('//')) return false
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) return false
  return true
}

/**
 * Drill-down is allowed only for safe /app hrefs and never when the KPI is in
 * technical `error` state (L1). `undefined` remains navigable when an href exists.
 */
export function canNavigateKpiDrilldown(kpi: Pick<OperationalKpiCard, 'href' | 'state'>): boolean {
  if (kpi.state === 'error') return false
  return isSafeAppHref(kpi.href)
}

export function formatKpiDisplay(kpi: OperationalKpiCard, unavailableLabel: string): string {
  if (kpi.state === 'undefined' || kpi.value === null) {
    return unavailableLabel
  }
  if (kpi.state === 'error') {
    return unavailableLabel
  }
  return kpi.display_value ?? String(kpi.value)
}

export function groupKpisByDomain(kpis: OperationalKpiCard[]): Array<{
  group: DomainGroupKey
  items: OperationalKpiCard[]
}> {
  const buckets = new Map<DomainGroupKey, OperationalKpiCard[]>()
  for (const group of DOMAIN_GROUP_ORDER) {
    buckets.set(group, [])
  }
  for (const kpi of kpis) {
    const group = domainToGroup(kpi.domain)
    buckets.get(group)?.push(kpi)
  }
  return DOMAIN_GROUP_ORDER.map((group) => ({
    group,
    items: buckets.get(group) ?? [],
  })).filter((entry) => entry.items.length > 0)
}
