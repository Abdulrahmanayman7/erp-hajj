import { describe, expect, it } from 'vitest'

import type { OperationalKpiCard } from '@/modules/operational-kpis/types/operationalKpis'

import {
  ACCOMMODATION_PANEL_IDS,
  PROJECT_PANEL_IDS,
  assertNoUnsupportedPanelMetrics,
  pickKpisById,
} from './executiveDashboardPanels'

function card(
  partial: Partial<OperationalKpiCard> & Pick<OperationalKpiCard, 'id' | 'domain' | 'state'>,
): OperationalKpiCard {
  return {
    label: partial.id,
    value: partial.state === 'ok' ? 1 : null,
    display_value: partial.state === 'ok' ? '1' : null,
    href: null,
    ...partial,
  }
}

describe('executiveDashboardPanels', () => {
  it('picks A02/A03 in catalog order and omits missing ids', () => {
    const picked = pickKpisById(
      [
        card({ id: 'A03', domain: 'accommodation', state: 'ok', value: 3, display_value: '3' }),
        card({ id: 'T01', domain: 'transport', state: 'ok' }),
        card({ id: 'A02', domain: 'accommodation', state: 'ok', value: 6, display_value: '6' }),
      ],
      ACCOMMODATION_PANEL_IDS,
    )
    expect(picked.map((k) => k.id)).toEqual(['A02', 'A03'])
  })

  it('picks J07/J09 and preserves undefined state without inventing planned progress', () => {
    const picked = pickKpisById(
      [
        card({ id: 'J07', domain: 'season_projects', state: 'ok', value: 47.5, display_value: '47.5' }),
        card({ id: 'J09', domain: 'season_projects', state: 'undefined', value: null, display_value: null }),
      ],
      PROJECT_PANEL_IDS,
    )
    expect(picked.map((k) => k.id)).toEqual(['J07', 'J09'])
    expect(picked[1]?.state).toBe('undefined')
  })

  it('returns empty when domain KPIs are omitted', () => {
    expect(pickKpisById([card({ id: 'T01', domain: 'transport', state: 'ok' })], ACCOMMODATION_PANEL_IDS)).toEqual(
      [],
    )
  })

  it('flags unsupported occupancy % and planned-progress fields (U01/U02)', () => {
    expect(
      assertNoUnsupportedPanelMetrics({
        panels: { occupancy_percent: 90, planned_progress: 10 },
      }),
    ).toEqual(expect.arrayContaining(['occupancy_percent', 'planned_progress']))
    expect(
      assertNoUnsupportedPanelMetrics({
        panels: {
          season: { id: 1, name: 's', status: 'active', status_label: 'نشط' },
          accommodation: {
            kpis: [card({ id: 'A02', domain: 'accommodation', state: 'ok', value: 1, display_value: '1' })],
          },
        },
      }),
    ).toEqual([])
  })
})
