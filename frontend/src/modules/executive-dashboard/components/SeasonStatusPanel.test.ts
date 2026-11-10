import { createApp, defineComponent, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'
import { executiveDashboardAr } from '@/locales/executiveDashboardAr'
import { operationalKpisAr } from '@/locales/operationalKpisAr'

import SeasonStatusPanel from './SeasonStatusPanel.vue'

const messages = {
  ar: {
    ...ar,
    nav: { ...ar.nav, ...executiveDashboardAr.nav },
    executiveDashboard: executiveDashboardAr.executiveDashboard,
    operationalKpis: operationalKpisAr.operationalKpis,
  },
}

const cleanups: Array<() => void> = []
afterEach(() => {
  cleanups.splice(0).forEach((fn) => fn())
})

async function mountPanel() {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () =>
          h(SeasonStatusPanel, {
            season: {
              id: 42,
              name: 'موسم الاختبار',
              status: 'active',
              status_label: 'نشط',
            },
          })
      },
    }),
  )
  app.use(
    createI18n({
      legacy: false,
      locale: 'ar',
      messages,
    }),
  )
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  await nextTick()
  return host
}

describe('SeasonStatusPanel', () => {
  it('renders season id, name and authorized status label in RTL', async () => {
    const host = await mountPanel()
    expect(host.querySelector('[dir="rtl"]')).not.toBeNull()
    expect(host.textContent).toContain('حالة الموسم')
    expect(host.textContent).toContain('42')
    expect(host.textContent).toContain('موسم الاختبار')
    expect(host.textContent).toContain('نشط')
    expect(host.textContent).not.toContain('occupancy_percent')
  })
})
