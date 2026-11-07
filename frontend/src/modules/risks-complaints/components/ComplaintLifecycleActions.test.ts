import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { risksComplaintsAr } from '@/locales/risksComplaintsAr'

import type { OperationalComplaint } from '../types/complaints'

const mockedPermissions = ref<string[]>([
  'complaints.close',
  'complaints.reopen',
  'complaints.assign',
  'complaints.resolve',
])

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (permission: string) => mockedPermissions.value.includes(permission),
    canAny: () => false,
    canAll: () => false,
    hasRole: () => false,
    permissions: mockedPermissions,
  }),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    selectedSeasonId: ref(1),
  }),
}))

const mockedEmployeeId = ref<number | null>(null)

vi.mock('@/modules/auth/queries/useCurrentUserQuery', () => ({
  useCurrentUserQuery: () => ({
    data: {
      get value() {
        return { id: 9, name: 'u', email: 'u@x', employee_id: mockedEmployeeId.value }
      },
    },
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

vi.mock('../mutations/useComplaintMutations', () => ({
  useUpdateComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useAssignComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useEscalateComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useResolveComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useCloseComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useReopenComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
  useDeleteComplaintMutation: () => ({ mutateAsync: vi.fn(), isPending: ref(false) }),
}))

vi.mock('@/modules/employees/api/employeesApi', () => ({
  listEmployees: vi.fn(async () => ({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
  })),
}))

vi.mock('@/shared/components/AppRemoteSelect.vue', () => ({
  default: defineComponent({
    name: 'AppRemoteSelectStub',
    props: ['modelValue'],
    emits: ['update:modelValue'],
    setup() {
      return () => h('div', { 'data-testid': 'remote-select-stub' })
    },
  }),
}))

import ComplaintLifecycleActions from './ComplaintLifecycleActions.vue'

const cleanups: Array<() => void> = []
afterEach(() => {
  mockedPermissions.value = [
    'complaints.close',
    'complaints.reopen',
    'complaints.assign',
    'complaints.resolve',
  ]
  mockedEmployeeId.value = null
  cleanups.splice(0).forEach((fn) => fn())
})

function baseComplaint(overrides: Partial<OperationalComplaint> = {}): OperationalComplaint {
  return {
    id: 1,
    season_id: 1,
    complaint_number: 'CMP-2026-000001',
    title: 'شكوى',
    body: 'نص',
    category_code: 'service',
    status: 'resolved',
    assignee_employee_id: 2,
    escalation_level: 0,
    escalated_to_employee_id: null,
    escalate_reason: null,
    escalated_at: null,
    resolution_text: 'حل',
    resolved_at: null,
    resolved_by: null,
    closed_at: null,
    closed_by: null,
    close_reason: null,
    reopened_at: null,
    reopened_by: null,
    reopen_reason: null,
    season_pilgrim_id: null,
    group_id: null,
    facility_id: null,
    transport_season_vehicle_id: null,
    project_id: null,
    created_at: null,
    updated_at: null,
    created_by: null,
    assignee_employee: null,
    escalated_to_employee: null,
    notes: [],
    ...overrides,
  }
}

function mountActions(complaint: OperationalComplaint) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(ComplaintLifecycleActions, { complaint })
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

describe('ComplaintLifecycleActions sensitive permissions', () => {
  it('shows close when resolved and complaints.close granted', async () => {
    const host = mountActions(baseComplaint({ status: 'resolved' }))
    await nextTick()
    expect(host.querySelector('[data-testid="complaint-close-btn"]')).not.toBeNull()
  })

  it('hides close without sensitive complaints.close', async () => {
    mockedPermissions.value = ['complaints.view', 'complaints.resolve']
    const host = mountActions(baseComplaint({ status: 'resolved' }))
    await nextTick()
    expect(host.querySelector('[data-testid="complaint-close-btn"]')).toBeNull()
  })

  it('shows reopen when closed and complaints.reopen granted', async () => {
    const host = mountActions(baseComplaint({ status: 'closed' }))
    await nextTick()
    expect(host.querySelector('[data-testid="complaint-reopen-btn"]')).not.toBeNull()
  })

  it('hides reopen without sensitive complaints.reopen', async () => {
    mockedPermissions.value = ['complaints.view', 'complaints.close']
    const host = mountActions(baseComplaint({ status: 'closed' }))
    await nextTick()
    expect(host.querySelector('[data-testid="complaint-reopen-btn"]')).toBeNull()
  })

  it('shows escalate for assignee linked user without complaints.escalate', async () => {
    mockedPermissions.value = ['complaints.view', 'complaints.resolve']
    mockedEmployeeId.value = 2
    const host = mountActions(baseComplaint({ status: 'in_progress', assignee_employee_id: 2 }))
    await nextTick()
    expect(host.querySelector('[data-testid="complaint-escalate-btn"]')).not.toBeNull()
  })

  it('hides escalate when neither escalate permission nor assignee', async () => {
    mockedPermissions.value = ['complaints.view', 'complaints.resolve']
    mockedEmployeeId.value = 99
    const host = mountActions(baseComplaint({ status: 'in_progress', assignee_employee_id: 2 }))
    await nextTick()
    expect(host.querySelector('[data-testid="complaint-escalate-btn"]')).toBeNull()
  })
})
