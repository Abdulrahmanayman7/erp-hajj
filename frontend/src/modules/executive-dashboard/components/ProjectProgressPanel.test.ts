import { createApp, defineComponent, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'
import { executiveDashboardAr } from '@/locales/executiveDashboardAr'
import { operationalKpisAr } from '@/locales/operationalKpisAr'

import ProjectProgressPanel from './ProjectProgressPanel.vue'

const messages = {
  ar: {
    ...ar,
    executiveDashboard: executiveDashboardAr.executiveDashboard,
    operationalKpis: operationalKpisAr.operationalKpis,
  },
}

const cleanups: Array<() => void> = []
afterEach(() => {
  cleanups.splice(0).forEach((fn) => fn())
})

async function mountPanel(kpis: Array<Record<string, unknown>>) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(ProjectProgressPanel, { kpis })
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

describe('ProjectProgressPanel', () => {
  it('renders J07/J09 and undefined state without planned progress fields', async () => {
    const host = await mountPanel([
      {
        id: 'J07',
        domain: 'season_projects',
        label: 'متوسط التقدم',
        value: 47.5,
        display_value: '47.5',
        state: 'ok',
        href: null,
      },
      {
        id: 'J09',
        domain: 'season_projects',
        label: 'نسبة الإكمال',
        value: null,
        display_value: null,
        state: 'undefined',
        href: null,
      },
    ])
    expect(host.textContent).toContain('تقدّم المشاريع')
    expect(host.textContent).toContain('J07')
    expect(host.textContent).toContain('47.5')
    expect(host.textContent).toContain('غير معرّف')
    expect(host.textContent).not.toContain('planned_progress')
    expect(host.textContent).not.toContain('progress_lag')
  })
})
