import { describe, expect, it } from 'vitest'

import type { DashboardKpis } from '../types/dashboard'
import {
  effectiveSeverity,
  formatDashboardCalendar,
  friendlyTimezone,
  getTopKpis,
  hasWorkMetrics,
  isDashboardDaytime,
  isOperationallyEmpty,
  isSafeAppHref,
  TOP_KPI_ORDER,
} from './dashboardDisplay'

describe('dashboardDisplay', () => {
  describe('getTopKpis', () => {
    it('returns only present keys in fixed TOP_KPI_ORDER', () => {
      const kpis: DashboardKpis = {
        meetings_today: {
          value: 2,
          label: 'اجتماعات اليوم',
          severity: 'info',
          href: '/app/meetings',
        },
        tasks_overdue: {
          value: 1,
          label: 'المهام المتأخرة',
          severity: 'critical',
          href: '/app/tasks?overdue=1',
        },
        tasks_open: {
          value: 9,
          label: 'المهام المفتوحة',
          severity: 'info',
          href: '/app/tasks',
        },
      }

      const top = getTopKpis(kpis)

      expect(top.map((e) => e.key)).toEqual(['tasks_overdue', 'meetings_today'])
      expect(TOP_KPI_ORDER.indexOf('tasks_overdue')).toBeLessThan(
        TOP_KPI_ORDER.indexOf('meetings_today'),
      )
    })

    it('returns empty array when kpis missing or empty', () => {
      expect(getTopKpis(undefined)).toEqual([])
      expect(getTopKpis({})).toEqual([])
      expect(getTopKpis([])).toEqual([])
    })
  })

  describe('hasWorkMetrics', () => {
    it('is true when my_tasks or work KPI keys exist', () => {
      expect(
        hasWorkMetrics({}, { my_tasks: { open: 1, overdue: 0, href: '/app/tasks' } }),
      ).toBe(true)
      expect(
        hasWorkMetrics(
          {
            tasks_open: {
              value: 3,
              label: 'مفتوحة',
              severity: 'info',
              href: '/app/tasks',
            },
          },
          {},
        ),
      ).toBe(true)
    })

    it('is false for empty arrays or unrelated KPIs', () => {
      expect(hasWorkMetrics([], [])).toBe(false)
      expect(
        hasWorkMetrics(
          {
            inventory_low: {
              value: 1,
              label: 'منخفض',
              severity: 'warning',
              href: '/app/inventory',
            },
          },
          {},
        ),
      ).toBe(false)
    })
  })

  describe('isSafeAppHref', () => {
    it('allows internal /app paths', () => {
      expect(isSafeAppHref('/app')).toBe(true)
      expect(isSafeAppHref('/app/tasks?overdue=1')).toBe(true)
      expect(isSafeAppHref('/app/notifications')).toBe(true)
    })

    it('rejects external and unsafe hrefs', () => {
      expect(isSafeAppHref('https://evil.example/app')).toBe(false)
      expect(isSafeAppHref('//evil.example')).toBe(false)
      expect(isSafeAppHref('/login')).toBe(false)
      expect(isSafeAppHref('javascript:alert(1)')).toBe(false)
      expect(isSafeAppHref(null)).toBe(false)
      expect(isSafeAppHref('')).toBe(false)
    })
  })

  describe('isOperationallyEmpty', () => {
    it('treats backend empty-array maps as operationally empty', () => {
      expect(
        isOperationallyEmpty({
          kpis: [] as unknown as DashboardKpis,
          attention: [],
          today: [] as unknown as Record<string, never>,
          work: [] as unknown as Record<string, never>,
          resources: [] as unknown as Record<string, never>,
        }),
      ).toBe(true)
    })

    it('treats notifications-only payload as empty operations', () => {
      expect(
        isOperationallyEmpty({
          kpis: {},
          attention: [],
          today: {},
          work: {},
          resources: {},
        }),
      ).toBe(true)
    })

    it('is false when any KPI or personal block exists', () => {
      expect(
        isOperationallyEmpty({
          kpis: {
            tasks_overdue: {
              value: 0,
              label: 'x',
              severity: 'info',
              href: '/app/tasks',
            },
          },
        }),
      ).toBe(false)

      expect(
        isOperationallyEmpty({
          kpis: {},
          work: { my_tasks: { open: 1, overdue: 0, href: '/app/tasks' } },
        }),
      ).toBe(false)
    })
  })

  it('presents zero KPIs as neutral even when their category is critical', () => {
    expect(effectiveSeverity('critical', 0)).toBe('info')
    expect(effectiveSeverity('warning', 0)).toBe('info')
    expect(effectiveSeverity('critical', 2)).toBe('critical')
  })

  it('uses a friendly tenant timezone label', () => {
    expect(friendlyTimezone('Asia/Riyadh')).toBe('الرياض')
    expect(friendlyTimezone('Pacific/Honolulu')).toBe('Pacific/Honolulu')
  })

  it('treats morning and afternoon hours as daytime', () => {
    expect(isDashboardDaytime(8)).toBe(true)
    expect(isDashboardDaytime(21)).toBe(false)
    expect(isDashboardDaytime(null)).toBe(true)
  })

  it('formats a gregorian date card in the tenant timezone', () => {
    const parts = formatDashboardCalendar('Asia/Riyadh')
    expect(parts.weekday.length).toBeGreaterThan(0)
    expect(parts.day.length).toBeGreaterThan(0)
    expect(parts.gregorian.length).toBeGreaterThan(0)
    if (parts.hijri) {
      expect(parts.hijri).not.toBe(parts.gregorian)
    }
  })
})
