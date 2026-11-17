import { createApp, nextTick, ref } from 'vue'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory, createRouter } from 'vue-router'
import { LayoutDashboard, Users } from 'lucide-vue-next'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { scrollContainerIntoView } from '@/shared/utils/scrollContainerIntoView'

vi.mock('@/shared/utils/scrollContainerIntoView', async () => {
  const actual = await vi.importActual<typeof import('@/shared/utils/scrollContainerIntoView')>(
    '@/shared/utils/scrollContainerIntoView',
  )
  return {
    ...actual,
    scrollContainerIntoView: vi.fn(),
  }
})

vi.mock('@/modules/auth/queries/useCurrentUserQuery', () => ({
  useCurrentUserQuery: () => ({
    data: ref({
      id: 1,
      name: 'Owner',
      roles: [{ id: 1, code: 'tenant_owner', name: 'Owner' }],
      permissions: ['dashboard.view', 'users.view'],
      is_platform_user: false,
    }),
  }),
}))

vi.mock('@/modules/auth/mutations/useLogoutMutation', () => ({
  useLogoutMutation: () => ({ mutate: vi.fn(), isPending: ref(false) }),
}))

vi.mock('@/shared/composables/useSidebarCollapse', () => ({
  useSidebarCollapse: () => ({
    collapsed: ref(false),
    toggleCollapsed: vi.fn(),
  }),
}))

vi.mock('@/shared/composables/useAppNavigation', () => ({
  resolveActiveMultiItemGroupKey: () => null,
  useAppNavigation: () => ({
    navGroups: ref([
      {
        key: 'home',
        labelKey: 'nav.home',
        items: [{ key: 'dashboard', labelKey: 'nav.dashboard', to: '/app', icon: LayoutDashboard }],
      },
      {
        key: 'system',
        labelKey: 'nav.systemAdmin',
        items: [
          { key: 'users', labelKey: 'nav.users', to: '/app/users', icon: Users },
          { key: 'roles', labelKey: 'nav.roles', to: '/app/roles', icon: Users },
        ],
      },
    ]),
    flatItems: ref([]),
    isItemActive: () => false,
  }),
}))

vi.mock('@/shared/components/UserAvatar.vue', () => ({
  default: { name: 'UserAvatarStub', template: '<div />' },
}))

import AppSidebar from './AppSidebar.vue'

describe('AppSidebar accordion auto-scroll', () => {
  const apps: Array<ReturnType<typeof createApp>> = []

  beforeEach(() => {
    vi.mocked(scrollContainerIntoView).mockClear()
  })

  afterEach(() => {
    apps.splice(0).forEach((app) => app.unmount())
  })

  it('scrolls the sidebar nav container when expanding a group', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/app', component: { template: '<div />' } },
        { path: '/app/users', component: { template: '<div />' } },
        { path: '/app/roles', component: { template: '<div />' } },
      ],
    })
    await router.push('/app')
    await router.isReady()

    const i18n = createI18n({
      legacy: false,
      locale: 'ar',
      messages: {
        ar: {
          shell: { expandSidebar: 'توسيع', collapseSidebar: 'طي' },
          auth: {
            companyName: 'رفيعة',
            companyNameEn: 'Rafeea',
            userFallback: 'مستخدم',
            systemManager: 'مدير',
            logout: 'خروج',
          },
          app: { tagline: 'ERP' },
          nav: {
            home: 'الرئيسية',
            systemAdmin: 'إدارة النظام',
            dashboard: 'لوحة',
            users: 'المستخدمون',
            roles: 'الأدوار',
          },
        },
      },
    })

    const host = document.createElement('div')
    document.body.appendChild(host)
    const app = createApp(AppSidebar)
    app.use(router)
    app.use(i18n)
    app.mount(host)
    apps.push(app)

    await nextTick()
    const trigger = host.querySelector('button.sidebar-dropdown-trigger') as HTMLButtonElement | null
    expect(trigger).not.toBeNull()
    trigger!.click()
    await nextTick()
    await new Promise((resolve) => requestAnimationFrame(() => resolve(undefined)))

    expect(scrollContainerIntoView).toHaveBeenCalled()
    const [container] = vi.mocked(scrollContainerIntoView).mock.calls.at(-1)!
    expect((container as HTMLElement).getAttribute('data-test')).toBe('sidebar-nav-scroll')
  })
})
