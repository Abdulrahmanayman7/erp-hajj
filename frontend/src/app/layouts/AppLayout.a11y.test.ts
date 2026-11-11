import { createApp, defineComponent, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { ar } from '@/locales/ar'

vi.mock('@/shared/composables/useAppNavigation', () => ({
  useAppNavigation: () => ({
    flatItems: { value: [] },
  }),
}))

vi.mock('@/shared/composables/useMobileMore', () => ({
  useMobileMore: () => ({
    moreOpen: { value: false },
    closeMore: () => undefined,
  }),
}))

vi.mock('@/shared/components/AppBottomNav.vue', () => ({
  default: defineComponent({ name: 'AppBottomNavStub', render: () => null }),
}))
vi.mock('@/shared/components/AppConfirmDialog.vue', () => ({
  default: defineComponent({ name: 'AppConfirmDialogStub', render: () => null }),
}))
vi.mock('@/shared/components/AppMobileMoreSheet.vue', () => ({
  default: defineComponent({ name: 'AppMobileMoreSheetStub', render: () => null }),
}))
vi.mock('@/shared/components/AppNavigationRail.vue', () => ({
  default: defineComponent({ name: 'AppNavigationRailStub', render: () => null }),
}))
vi.mock('@/shared/components/AppPullToRefresh.vue', () => ({
  default: defineComponent({ name: 'AppPullToRefreshStub', render: () => null }),
}))
vi.mock('@/shared/components/AppToastHost.vue', () => ({
  default: defineComponent({ name: 'AppToastHostStub', render: () => null }),
}))
vi.mock('./AppFooter.vue', () => ({
  default: defineComponent({ name: 'AppFooterStub', render: () => null }),
}))
vi.mock('./AppSidebar.vue', () => ({
  default: defineComponent({ name: 'AppSidebarStub', render: () => null }),
}))
vi.mock('./AppTopbar.vue', () => ({
  default: defineComponent({ name: 'AppTopbarStub', render: () => null }),
}))

import AppLayout from './AppLayout.vue'

const cleanups: Array<() => void> = []
afterEach(() => {
  cleanups.splice(0).forEach((fn) => fn())
})

describe('AppLayout accessibility shell', () => {
  it('exposes an Arabic skip-to-content link targeting the main landmark', async () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/', component: { template: '<div />' } }],
    })
    await router.push('/')
    await router.isReady()

    const app = createApp({
      setup() {
        return () => h(AppLayout, null, { default: () => h('p', 'محتوى') })
      },
    })
    app.use(createI18n({ legacy: false, locale: 'ar', messages: { ar } }))
    app.use(router)
    app.mount(host)
    cleanups.push(() => {
      app.unmount()
      host.remove()
    })
    await nextTick()

    const skip = host.querySelector('[data-test="skip-to-content"]') as HTMLAnchorElement | null
    const main = host.querySelector('#app-main-content') as HTMLElement | null
    expect(skip).not.toBeNull()
    expect(skip?.getAttribute('href')).toBe('#app-main-content')
    expect(skip?.textContent?.trim()).toBe('تخطي إلى المحتوى الرئيسي')
    expect(main).not.toBeNull()
    expect(main?.tagName.toLowerCase()).toBe('main')
  })
})
