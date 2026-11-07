import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { risksComplaintsAr } from '@/locales/risksComplaintsAr'

const getOperationalLinksCapabilities = vi.fn()

vi.mock('../api/risksApi', () => ({
  getOperationalLinksCapabilities: (...args: unknown[]) => getOperationalLinksCapabilities(...args),
}))

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: () => false,
    canAny: () => false,
    canAll: () => false,
    hasRole: () => false,
    permissions: ref([]),
  }),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    selectedSeasonId: ref(3),
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

vi.mock('@/modules/accommodation/api/accommodationApi', () => ({
  listSeasonFacilities: vi.fn(async () => ({ data: [] })),
  listFacilities: vi.fn(async () => ({ data: [] })),
}))

vi.mock('@/modules/season-pilgrims/api/pilgrimGroupsApi', () => ({
  listPilgrimGroups: vi.fn(async () => []),
}))

vi.mock('@/modules/season-pilgrims/api/seasonPilgrimsApi', () => ({
  listSeasonPilgrims: vi.fn(async () => ({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
  })),
}))

vi.mock('@/modules/transportation/api/seasonFleetApi', () => ({
  listSeasonVehicles: vi.fn(async () => ({ data: [] })),
}))

vi.mock('@/shared/components/AppRemoteSelect.vue', () => ({
  default: defineComponent({
    name: 'AppRemoteSelectStub',
    setup() {
      return () => h('div', { 'data-testid': 'remote-select-stub' })
    },
  }),
}))

vi.mock('@/shared/components/AppSelect.vue', () => ({
  default: defineComponent({
    name: 'AppSelectStub',
    setup() {
      return () => h('div', { 'data-testid': 'select-stub' })
    },
  }),
}))

import OperationalLinksFields from './OperationalLinksFields.vue'

const cleanups: Array<() => void> = []
afterEach(() => {
  getOperationalLinksCapabilities.mockReset()
  cleanups.splice(0).forEach((fn) => fn())
})

function mountLinks() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const model = {
    season_pilgrim_id: '' as const,
    group_id: '' as const,
    facility_id: '' as const,
    transport_season_vehicle_id: '' as const,
    project_id: '' as const,
  }
  const app = createApp(
    defineComponent({
      setup() {
        return () =>
          h(OperationalLinksFields, {
            modelValue: model,
            'onUpdate:modelValue': () => undefined,
          })
      },
    }),
  )
  app.use(
    createI18n({
      legacy: false,
      locale: 'ar',
      messages: { ar: { risks: risksComplaintsAr.risks, complaints: risksComplaintsAr.complaints } },
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  return host
}

describe('OperationalLinksFields', () => {
  it('disables project selection when SeasonProjects API is unavailable', async () => {
    getOperationalLinksCapabilities.mockResolvedValue({
      season_projects_available: false,
      season_projects_reason: 'season_projects_api_unavailable',
    })

    const host = mountLinks()
    await nextTick()
    await Promise.resolve()
    await nextTick()

    expect(getOperationalLinksCapabilities).toHaveBeenCalled()
    expect(host.querySelector('[data-testid="project-link-unavailable"]')).not.toBeNull()
    expect(host.textContent).toContain(risksComplaintsAr.risks.links.projectUnavailable)
  })
})
