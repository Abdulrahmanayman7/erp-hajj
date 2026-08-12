import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as notificationsApi from './notificationsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}))

import { apiGet, apiPost } from '@/shared/api/http'

const sampleNotification = {
  id: 1,
  type: 'TASK_ASSIGNED',
  title: 'مهمة جديدة',
  body: 'تم إسناد مهمة إليك',
  severity: 'info' as const,
  entity_type: 'task',
  entity_id: 12,
  read_at: null,
  created_at: '2026-08-12T10:00:00+00:00',
  is_read: false,
}

describe('notificationsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
  })

  it('lists notifications with filters as query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [sampleNotification],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await notificationsApi.listNotifications({
      unread_only: true,
      type: 'TASK_ASSIGNED',
      severity: 'info',
      created_from: '2026-08-01',
      created_to: '2026-08-12',
      page: 2,
      per_page: 15,
    })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.type).toBe('TASK_ASSIGNED')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/notifications?unread_only=1&type=TASK_ASSIGNED&severity=info&created_from=2026-08-01&created_to=2026-08-12&page=2&per_page=15',
    )
  })

  it('gets unread count', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: { unread_count: 3 },
    })

    const result = await notificationsApi.getUnreadCount()

    expect(result.unread_count).toBe(3)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/notifications/unread-count')
  })

  it('gets a single notification', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: sampleNotification,
    })

    const result = await notificationsApi.getNotification(1)

    expect(result.id).toBe(1)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/notifications/1')
  })

  it('marks one and all as read', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { ...sampleNotification, is_read: true, read_at: '2026-08-12T11:00:00+00:00' },
    })

    await notificationsApi.markNotificationRead(1)
    await notificationsApi.markAllNotificationsRead()

    expect(apiPost).toHaveBeenCalledWith('/api/v1/notifications/1/read', {})
    expect(apiPost).toHaveBeenCalledWith('/api/v1/notifications/read-all', {})
  })

  it('omits empty filters from the query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [],
      meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    })

    await notificationsApi.listNotifications({
      unread_only: false,
      type: '',
      severity: '',
      page: 1,
    })

    expect(apiGet).toHaveBeenCalledWith('/api/v1/notifications?page=1')
  })
})
