import { describe, expect, it } from 'vitest'

import type { DashboardKpis } from '../types/dashboard'
import {
  getTopKpis,
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
})
