import { createApp, defineComponent, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'
import { executiveDashboardAr } from '@/locales/executiveDashboardAr'
import { operationalKpisAr } from '@/locales/operationalKpisAr'

import AccommodationCountsPanel from './AccommodationCountsPanel.vue'

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
        return () => h(AccommodationCountsPanel, { kpis })
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

describe('AccommodationCountsPanel', () => {
  it('renders A02/A03 display_value and omits invented occupancy percentage', async () => {
    const host = await mountPanel([
      {
        id: 'A02',
        domain: 'accommodation',
        label: 'مخصص',
        value: 6,
        display_value: '6',
        state: 'ok',
        href: null,
      },
      {
        id: 'A03',
        domain: 'accommodation',
        label: 'مشغول',
        value: 3,
        display_value: '3',
        state: 'ok',
        href: null,
      },
    ])
    expect(host.textContent).toContain('عدّادات الإقامة')
    expect(host.textContent).toContain('A02')
    expect(host.textContent).toContain('A03')
    expect(host.textContent).toContain('6')
    expect(host.textContent).toContain('3')
    expect(host.textContent).not.toContain('occupancy_percent')
    expect(host.textContent).not.toContain('%')
  })

  it('renders nothing when domain KPIs are omitted', async () => {
    const host = await mountPanel([])
    expect(host.querySelector('[aria-label="accommodation counts panel"]')).toBeNull()
  })
})
