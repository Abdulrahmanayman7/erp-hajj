import { describe, expect, it } from 'vitest'

import type { OperationalKpiCard } from '../types/operationalKpis'
import {
  DRILLDOWN_DISCLAIMER_AR,
  canNavigateKpiDrilldown,
  formatKpiDisplay,
  groupKpisByDomain,
  isSafeAppHref,
} from './operationalKpisDisplay'

function card(partial: Partial<OperationalKpiCard> & Pick<OperationalKpiCard, 'id' | 'state'>): OperationalKpiCard {
  return {
    domain: 'transport',
    label: 'x',
    value: partial.state === 'ok' ? 1 : null,
    display_value: partial.state === 'ok' ? '1' : null,
    href: '/app/transport-movements',
    ...partial,
  }
}

describe('operationalKpisDisplay', () => {
  it('accepts only safe /app hrefs', () => {
    expect(isSafeAppHref('/app/transport-movements')).toBe(true)
    expect(isSafeAppHref('https://evil.example')).toBe(false)
    expect(isSafeAppHref('//evil')).toBe(false)
    expect(isSafeAppHref(null)).toBe(false)
  })

  it('shows غير متاح for undefined and error states', () => {
    const unavailable = 'غير متاح'
    expect(formatKpiDisplay(card({ id: 'J09', state: 'undefined' }), unavailable)).toBe(unavailable)
    expect(formatKpiDisplay(card({ id: 'T01', state: 'error' }), unavailable)).toBe(unavailable)
  })

  it('prefers server display_value for ok cards', () => {
    expect(
      formatKpiDisplay(
        card({
          id: 'P07',
          domain: 'purchase_orders',
          state: 'ok',
          value: 10.5,
          display_value: '10.5',
          href: '/app/purchase-orders',
        }),
        'غير متاح',
      ),
    ).toBe('10.5')
  })

  it('groups procurement domains together and omits empty groups', () => {
    const groups = groupKpisByDomain([
      card({
        id: 'P01',
        domain: 'purchase_requests',
        state: 'ok',
        href: '/app/purchase-requests',
      }),
      card({ id: 'T01', domain: 'transport', state: 'ok' }),
    ])
    expect(groups.map((g) => g.group)).toEqual(['transport', 'procurement'])
  })

  it('does not allow navigation for error-state cards even with a safe href (L1)', () => {
    expect(canNavigateKpiDrilldown(card({ id: 'T01', state: 'error' }))).toBe(false)
    expect(canNavigateKpiDrilldown(card({ id: 'T01', state: 'ok' }))).toBe(true)
    expect(canNavigateKpiDrilldown(card({ id: 'J09', state: 'undefined' }))).toBe(true)
    expect(canNavigateKpiDrilldown(card({ id: 'T01', state: 'ok', href: null }))).toBe(false)
    expect(canNavigateKpiDrilldown(card({ id: 'T01', state: 'ok', href: 'https://evil' }))).toBe(
      false,
    )
  })

  it('exposes the approved Arabic drill-down disclaimer (M1)', () => {
    expect(DRILLDOWN_DISCLAIMER_AR).toBe('قائمة عامة — قد لا تطابق فلتر المؤشر')
  })
})
