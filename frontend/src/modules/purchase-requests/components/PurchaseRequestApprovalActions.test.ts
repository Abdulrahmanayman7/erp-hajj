import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { ar } from '@/locales/ar'

import type { PurchaseRequest } from '../types/purchaseRequests'

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (permission: string) => mockedPermissions.value.includes(permission),
    canAny: () => false,
    canAll: () => false,
    hasRole: () => false,
    permissions: mockedPermissions,
  }),
}))

import PurchaseRequestApprovalActions from './PurchaseRequestApprovalActions.vue'

const mockedPermissions = ref<string[]>(['purchase_requests.approve'])

const cleanups: Array<() => void> = []
afterEach(() => {
  mockedPermissions.value = ['purchase_requests.approve']
  cleanups.splice(0).forEach((fn) => fn())
})

function basePr(overrides: Partial<PurchaseRequest> = {}): PurchaseRequest {
  return {
    id: 1,
    pr_number: 'PR-1',
    version_no: 1,
    status: 'under_review',
    pending_approval_engine: false,
    request_kind: 'administrative',
    season_id: null,
    title: 'طلب',
    purpose: 'غرض',
    priority: 'normal',
    urgency_justification: null,
    priority_verified_at: null,
    priority_verified_by: null,
    requested_by: { id: 2, name: 'مقدم' },
    beneficiary_organization_unit_id: 1,
    optional_supplier_id: null,
    currency: 'SAR',
    estimated_total_priced: '100',
    has_unpriced_lines: false,
    matrix_id: 1,
    matrix_version_no: 1,
    duplicate_warning_acknowledged_at: null,
    submitted_at: null,
    cancelled_at: null,
    cancelled_by: null,
    cancellation_reason: null,
    ...overrides,
  }
}

function mountActions(pr: PurchaseRequest) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(PurchaseRequestApprovalActions, { purchaseRequest: pr })
      },
    }),
  )
  app.use(
    createI18n({
      legacy: false,
      locale: 'ar',
      messages: { ar },
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  return host
}

describe('PurchaseRequestApprovalActions permission visibility', () => {
  it('shows approve button when under_review and permitted', async () => {
    const host = mountActions(basePr())
    await nextTick()
    expect(host.querySelector('[data-testid="pr-approve-btn"]')).not.toBeNull()
  })

  it('hides approve button without purchase_requests.approve', async () => {
    mockedPermissions.value = ['purchase_requests.view']
    const host = mountActions(basePr())
    await nextTick()
    expect(host.querySelector('[data-testid="pr-approve-btn"]')).toBeNull()
  })

  it('hides approve button when status is not under_review', async () => {
    const host = mountActions(basePr({ status: 'submitted' }))
    await nextTick()
    expect(host.querySelector('[data-testid="pr-approve-btn"]')).toBeNull()
  })

  it('hides cancel-approved when procurement is engaged', async () => {
    mockedPermissions.value = ['purchase_requests.cancel_approved']
    const host = mountActions(
      basePr({
        status: 'approved',
        procurement_engaged_at: '2026-11-02T10:00:00+00:00',
        procurement_engagement_ref: 'RFQ:1',
      }),
    )
    await nextTick()
    expect(host.textContent).not.toContain('إلغاء معتمد')
  })

  it('shows cancel-approved for approved PR without engagement', async () => {
    mockedPermissions.value = ['purchase_requests.cancel_approved']
    const host = mountActions(basePr({ status: 'approved', procurement_engaged_at: null }))
    await nextTick()
    expect(host.textContent).toContain('إلغاء معتمد')
  })
})
