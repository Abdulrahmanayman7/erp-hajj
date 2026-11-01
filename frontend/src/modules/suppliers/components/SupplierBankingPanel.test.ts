import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { ar } from '@/locales/ar'

vi.mock('@/shared/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: (permission: string) => mockedPermissions.value.includes(permission),
    canAny: () => false,
    canAll: () => false,
    hasRole: () => false,
    permissions: mockedPermissions,
  }),
}))

vi.mock('@/shared/composables/useToast', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }),
}))

vi.mock('../queries/useBankAccountsQuery', () => ({
  useSupplierBankAccountsQuery: () => ({
    data: mockedAccounts,
    isLoading: ref(false),
    isError: ref(false),
    refetch: vi.fn(),
  }),
}))

vi.mock('../mutations/useBankAccountMutations', () => ({
  useCreateBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useUpdatePendingBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useVerifyBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useRejectBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useRevokeBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useSupersedeBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useSetPrimaryBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useClearPrimaryBankAccountMutation: () => ({ isPending: ref(false), mutateAsync: vi.fn() }),
  useRevealBankAccountMutation: () => ({
    isPending: ref(false),
    mutateAsync: vi.fn(),
    reset: vi.fn(),
  }),
}))

import SupplierBankingPanel from './SupplierBankingPanel.vue'

const mockedPermissions = ref<string[]>(['suppliers.view', 'suppliers.manage_banking'])
const mockedAccounts = ref([
  {
    id: 7,
    supplier_id: 1,
    account_key: 'ak',
    version_no: 1,
    label: null,
    account_holder_name: 'شركة',
    bank_name: 'بنك',
    iban_masked: '****7519',
    account_masked: null,
    currency: 'SAR',
    status: 'pending',
    is_primary: false,
    is_current: true,
    supersedes_id: null,
    evidence_document_id: null,
    created_by: 1,
    verified_by: null,
    verified_at: null,
  },
])

const cleanups: Array<() => void> = []
afterEach(() => {
  mockedPermissions.value = ['suppliers.view', 'suppliers.manage_banking']
  cleanups.splice(0).forEach((fn) => fn())
})

function mountPanel() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  const app = createApp(
    defineComponent({
      setup() {
        return () =>
          h(SupplierBankingPanel, {
            supplier: {
              id: 1,
              display_name: 'مورد',
              status: 'active',
            } as never,
          })
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
  app.use(VueQueryPlugin, { queryClient })
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
    queryClient.clear()
  })
  return host
}

describe('SupplierBankingPanel', () => {
  it('renders RTL panel with masked IBAN and no plaintext', async () => {
    const host = mountPanel()
    await nextTick()
    const panel = host.querySelector('[data-testid="supplier-banking-panel"]')
    expect(panel?.getAttribute('dir')).toBe('rtl')
    expect(host.textContent).toContain('****7519')
    expect(host.textContent).not.toContain('SA0380000000608010167519')
    expect(host.querySelector('[data-testid="banking-iban-masked"]')?.textContent).toContain('****7519')
  })

  it('hides verify/reject for manage-only and hides reveal for verify-only', async () => {
    mockedPermissions.value = ['suppliers.view', 'suppliers.manage_banking']
    let host = mountPanel()
    await nextTick()
    expect(host.querySelector('[data-testid="banking-verify"]')).toBeNull()
    expect(host.querySelector('[data-testid="banking-reject"]')).toBeNull()
    expect(host.querySelector('[data-testid="banking-reveal"]')).toBeNull()
    expect(host.querySelector('[data-testid="banking-edit"]')).not.toBeNull()

    cleanups.splice(0).forEach((fn) => fn())
    mockedPermissions.value = ['suppliers.view', 'suppliers.verify_banking']
    host = mountPanel()
    await nextTick()
    expect(host.querySelector('[data-testid="banking-verify"]')).not.toBeNull()
    expect(host.querySelector('[data-testid="banking-reject"]')).not.toBeNull()
    expect(host.querySelector('[data-testid="banking-reveal"]')).toBeNull()
    expect(host.querySelector('[data-testid="banking-create"]')).toBeNull()
  })

  it('shows reveal control only with view_banking', async () => {
    mockedPermissions.value = ['suppliers.view', 'suppliers.view_banking']
    const host = mountPanel()
    await nextTick()
    expect(host.querySelector('[data-testid="banking-reveal"]')).not.toBeNull()
    expect(host.querySelector('[data-testid="banking-verify"]')).toBeNull()
  })
})
