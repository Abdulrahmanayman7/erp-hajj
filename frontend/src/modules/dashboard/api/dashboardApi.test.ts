import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as dashboardApi from './dashboardApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
}))

import { apiGet } from '@/shared/api/http'

describe('dashboardApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
  })

  it('gets dashboard snapshot from GET /api/v1/dashboard', async () => {
    const payload = {
      meta: {
        generated_at: '2026-08-12T18:00:00+03:00',
        timezone: 'Asia/Riyadh',
        sections: ['tasks'],
      },
      kpis: {
        tasks_overdue: {
          value: 3,
          label: 'المهام المتأخرة',
          severity: 'critical' as const,
          href: '/app/tasks?overdue=1',
        },
      },
      attention: [],
      today: {},
      work: {},
      resources: {},
      notifications: { unread_count: 2, href: '/app/notifications' },
    }

    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: payload,
    })

    const result = await dashboardApi.getDashboard()

    expect(result.kpis?.tasks_overdue?.value).toBe(3)
    expect(result.notifications?.unread_count).toBe(2)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/dashboard')
  })
})
