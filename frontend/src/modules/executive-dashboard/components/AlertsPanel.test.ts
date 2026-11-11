import { createApp, defineComponent, h, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { ar } from '@/locales/ar'
import { executiveDashboardAr } from '@/locales/executiveDashboardAr'

import type { ExecutiveAlert } from '../types/executiveDashboard'
import AlertsPanel from './AlertsPanel.vue'

const messages = {
  ar: {
    ...ar,
    executiveDashboard: executiveDashboardAr.executiveDashboard,
  },
}

const cleanups: Array<() => void> = []
afterEach(() => {
  cleanups.splice(0).forEach((fn) => fn())
})

function alert(over: Partial<ExecutiveAlert> = {}): ExecutiveAlert {
  return {
    id: 1,
    season_id: 9,
    rule_code: 'ALT-RISK-HIGH',
    status: 'open',
    severity: 'critical',
    title: 'خطر تشغيلي مرتفع: RSK-1',
    body: 'انقطاع مياه',
    source_entity_type: 'operational_risk',
    source_entity_id: 5,
    opened_at: '2026-11-10T00:00:00+00:00',
    last_seen_at: '2026-11-10T00:00:00+00:00',
    acknowledged_at: null,
    acknowledged_by: null,
    cleared_at: null,
    ...over,
  }
}

async function mountPanel(props: Record<string, unknown>) {
  const host = document.createElement('div')
  document.body.appendChild(host)
  const app = createApp(
    defineComponent({
      setup() {
        return () => h(AlertsPanel, props)
      },
    }),
  )
  app.use(createI18n({ legacy: false, locale: 'ar', messages }))
  app.mount(host)
  cleanups.push(() => {
    app.unmount()
    host.remove()
  })
  await nextTick()
  return host
}

describe('AlertsPanel', () => {
  it('renders counts, rule code, severity chip and Arabic copy in RTL', async () => {
    const host = await mountPanel({
      alerts: [alert()],
      openCount: 1,
      acknowledgedCount: 2,
      criticalCount: 1,
    })
    expect(host.querySelector('[dir="rtl"]')).not.toBeNull()
    expect(host.querySelector('[aria-label="لوحة التنبيهات التشغيلية"]')).not.toBeNull()
    expect(host.textContent).toContain('التنبيهات التشغيلية')
    expect(host.textContent).toContain('ALT-RISK-HIGH')
    expect(host.querySelector('[data-test="alerts-open-count"]')?.textContent?.trim()).toBe('1')
    expect(host.querySelector('[data-test="alerts-ack-count"]')?.textContent?.trim()).toBe('2')
    expect(host.querySelector('[data-test="alert-severity"]')?.textContent).toContain('حرج')
  })

  it('shows the empty state when there are no alerts', async () => {
    const host = await mountPanel({ alerts: [], openCount: 0, acknowledgedCount: 0, criticalCount: 0 })
    expect(host.querySelector('[data-test="alerts-empty"]')).not.toBeNull()
  })

  it('emits acknowledge only for open alerts', async () => {
    const onAck = vi.fn()
    const host = await mountPanel({
      alerts: [alert({ id: 1 }), alert({ id: 2, status: 'acknowledged' })],
      openCount: 1,
      acknowledgedCount: 1,
      criticalCount: 0,
      onAcknowledge: onAck,
    })
    const buttons = host.querySelectorAll('[data-test="alert-acknowledge"]')
    expect(buttons).toHaveLength(1)
    ;(buttons[0] as HTMLButtonElement).click()
    expect(onAck).toHaveBeenCalledWith(1)
    expect(host.querySelector('[data-test="alert-acknowledged-badge"]')).not.toBeNull()
  })

  it('never renders deferred alert rule codes', async () => {
    const host = await mountPanel({ alerts: [alert()], openCount: 1, acknowledgedCount: 0, criticalCount: 0 })
    for (const code of ['ALT-OCC-90', 'ALT-PROJ-LAG', 'ALT-COMP-SLA']) {
      expect(host.textContent).not.toContain(code)
    }
  })
})
