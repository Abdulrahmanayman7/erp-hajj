import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { risksComplaintsAr } from '@/locales/risksComplaintsAr'

import type { OperationalRisk } from '../types/risks'

const mockedPermissions = ref<string[]>(['risks.approve_assessment', 'risks.assign', 'risks.close'])
const mockedUserId = ref<number>(9)

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (permission: string) => mockedPermissions.value.includes(permission),
    canAny: () => false,
    canAll: () => false,
    hasRole: () => false,
    permissions: mockedPermissions,
  }),
}))

vi.mock('@/modules/auth/queries/useCurrentUserQuery', () => ({
  useCurrentUserQuery: () => ({
    data: { get value() { return { id: mockedUserId.value, name: 'u', email: 'u@x' } } },
  }),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    selectedSeasonId: ref(1),
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

vi.mock('@/shared/composables/useConfirm', () => ({
  useConfirm: () => ({ confirm: vi.fn(async () => true) }),
}))

vi.mock('@/shared/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn() }),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

vi.mock('../mutations/useRiskMutations', () => ({
  useApproveRiskAssessmentMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useAssignRiskMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useCloseRiskMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useDeleteRiskMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
}))

vi.mock('@/modules/employees/api/employeesApi', () => ({
  listEmployees: vi.fn(async () => ({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } })),
}))

vi.mock('@/shared/components/AppRemoteSelect.vue', () => ({
  default: defineComponent({
    name: 'AppRemoteSelectStub',
    setup() {
      return () => h('div', { 'data-testid': 'remote-select-stub' })
    },
  }),
}))

import RiskLifecycleActions from './RiskLifecycleActions.vue'

const cleanups: Array<() => void> = []
afterEach(() => {
  mockedPermissions.value = ['risks.approve_assessment', 'risks.assign', 'risks.close']
  mockedUserId.value = 9
  cleanups.splice(0).forEach((fn) => fn())
})

function baseRisk(overrides: Partial<OperationalRisk> = {}): OperationalRisk {
  return {
    id: 1,
    season_id: 1,
    risk_number: 'RSK-2026-000001',
    title: 'خطر',
    description: null,
    status: 'under_assessment',
    likelihood: 5,
    impact: 5,
    score: 25,
    severity_level: 'critical',
    closure_outcome: null,
    close_reason: null,
    owner_employee_id: null,
    season_pilgrim_id: null,
    group_id: null,
    facility_id: null,
    transport_season_vehicle_id: null,
    project_id: null,
    approved_at: null,
    created_at: null,
    updated_at: null,
    created_by: { id: 7, name: 'منشئ', email: 'c@x' },
    approved_by: null,
    owner_employee: null,
    treatment: null,
    ...overrides,
  }
}

function mountActions(risk: OperationalRisk) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(RiskLifecycleActions, { risk })
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
  app.use(VueQueryPlugin, { queryClient: new QueryClient() })
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  return host
}

describe('RiskLifecycleActions permissions', () => {
  it('shows approve for non-creator with approve_assessment', async () => {
    const host = mountActions(baseRisk())
    await nextTick()
    expect(host.querySelector('[data-testid="risk-approve-btn"]')).not.toBeNull()
  })

  it('hides approve without risks.approve_assessment', async () => {
    mockedPermissions.value = ['risks.view']
    const host = mountActions(baseRisk())
    await nextTick()
    expect(host.querySelector('[data-testid="risk-approve-btn"]')).toBeNull()
  })

  it('hides approve for creator even with permission', async () => {
    mockedUserId.value = 7
    const host = mountActions(baseRisk())
    await nextTick()
    expect(host.querySelector('[data-testid="risk-approve-btn"]')).toBeNull()
  })
})
