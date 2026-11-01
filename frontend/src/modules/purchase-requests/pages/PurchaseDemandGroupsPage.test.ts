import { createApp, defineComponent, h, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory, createRouter, RouterLink } from 'vue-router'
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
  listDemandGroups: vi.fn(async () => ({ data: [], meta: { total: 0 } })),
  createDemandGroup: vi.fn(),
}))

import PurchaseDemandGroupsPage from './PurchaseDemandGroupsPage.vue'

const mockedPermissions = ref<string[]>(['purchase_requests.view'])

const cleanups: Array<() => void> = []
afterEach(() => {
  mockedPermissions.value = ['purchase_requests.view']
  cleanups.splice(0).forEach((fn) => fn())
})

async function mountPage() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { template: '<div />' } },
      { path: '/app/purchase-demand-groups/:id', component: { template: '<div />' } },
    ],
  })
  await router.push('/')
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(PurchaseDemandGroupsPage)
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
  app.use(router)
  app.component('RouterLink', RouterLink)
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  await nextTick()
  return host
}

describe('PurchaseDemandGroupsPage', () => {
  it('hides create CTA without consolidate_planning', async () => {
    const host = await mountPage()
    expect(host.textContent).toContain('مجموعات تخطيط الطلبات')
    expect(host.textContent).not.toContain('مجموعة جديدة')
  })

  it('shows create CTA with consolidate_planning', async () => {
    mockedPermissions.value = [
      'purchase_requests.view',
      'purchase_requests.consolidate_planning',
    ]
    const host = await mountPage()
    expect(host.textContent).toContain('مجموعة جديدة')
  })
})
