import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { risksComplaintsAr } from '@/locales/risksComplaintsAr'

const getRiskTimeline = vi.fn()
const getComplaintTimeline = vi.fn()

vi.mock('../api/risksApi', () => ({
  getRiskTimeline: (...args: unknown[]) => getRiskTimeline(...args),
}))

vi.mock('../api/complaintsApi', () => ({
  getComplaintTimeline: (...args: unknown[]) => getComplaintTimeline(...args),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    selectedSeasonId: ref(7),
  }),
}))

vi.mock('pinia', async () => {
  const actual = await vi.importActual<typeof import('pinia')>('pinia')
  return {
    ...actual,
    storeToRefs: (store: { selectedSeasonId: unknown }) => ({
      selectedSeasonId: store.selectedSeasonId,
    }),
  }
})

import OperationalTimelinePanel from './OperationalTimelinePanel.vue'

const cleanups: Array<() => void> = []
afterEach(() => {
  getRiskTimeline.mockReset()
  getComplaintTimeline.mockReset()
  cleanups.splice(0).forEach((fn) => fn())
})

function mountPanel(entity: 'risk' | 'complaint', entityId: number) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(OperationalTimelinePanel, { entity, entityId })
      },
    }),
  )
  app.use(
    createI18n({
      legacy: false,
      locale: 'ar',
      messages: {
        ar: {
          risks: risksComplaintsAr.risks,
          complaints: risksComplaintsAr.complaints,
          audit: { events: { RISK_CREATED: 'تم إنشاء الخطر' } },
        },
      },
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  return host
}

describe('OperationalTimelinePanel', () => {
  it('loads risk timeline entries and renders them', async () => {
    getRiskTimeline.mockResolvedValue([
      {
        id: 11,
        event_type: 'RISK_CREATED',
        actor_label: 'أحمد',
        actor_user_id: 3,
        entity_type: 'operational_risk',
        entity_id: 1,
        entity_number: 'RSK-2026-000001',
        correlation_id: 'c1',
        created_at: '2026-11-07T10:00:00+00:00',
        summary: null,
      },
    ])

    const host = mountPanel('risk', 1)
    await nextTick()
    await Promise.resolve()
    await nextTick()

    expect(getRiskTimeline).toHaveBeenCalledWith(1, 7)
    expect(host.querySelector('[data-testid="timeline-entry-11"]')).not.toBeNull()
    expect(host.textContent).toContain('تم إنشاء الخطر')
  })

  it('shows empty state when timeline has no entries', async () => {
    getRiskTimeline.mockResolvedValue([])
    const host = mountPanel('risk', 2)
    await nextTick()
    await Promise.resolve()
    await nextTick()
    expect(host.textContent).toContain(risksComplaintsAr.risks.timeline.empty)
  })
})
