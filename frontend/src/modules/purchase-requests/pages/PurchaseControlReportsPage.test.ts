import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
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

vi.mock('../api/demandGroupsApi', () => ({
  fetchControlReport: vi.fn(async () => ({ summary: { count: 0 } })),
  downloadControlReportCsv: vi.fn(),
}))

import PurchaseControlReportsPage from './PurchaseControlReportsPage.vue'

const mockedPermissions = ref<string[]>(['purchase_requests.view_control_reports'])

const cleanups: Array<() => void> = []
afterEach(() => {
  mockedPermissions.value = ['purchase_requests.view_control_reports']
  cleanups.splice(0).forEach((fn) => fn())
})

async function mountPage() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(PurchaseControlReportsPage)
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
  await nextTick()
  await nextTick()
  return host
}

describe('PurchaseControlReportsPage', () => {
  it('renders four report tabs and CSV export with view_control_reports', async () => {
    const host = await mountPage()
    expect(host.textContent).toContain('تقارير اعتماد المشتريات')
    expect(host.textContent).toContain('زمن الاعتماد')
    expect(host.textContent).toContain('المتأخرات')
    expect(host.textContent).toContain('النتائج')
    expect(host.textContent).toContain('الامتثال والاستثناءات')
    expect(host.textContent).toContain('تصدير CSV')
  })

  it('hides CSV export without view_control_reports', async () => {
    mockedPermissions.value = []
    const host = await mountPage()
    expect(host.textContent).not.toContain('تصدير CSV')
  })
})
