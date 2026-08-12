import { apiGet, apiPost } from '@/shared/api/http'

import type {
  ListNotificationsParams,
  Notification,
  NotificationsListMeta,
  UnreadCountResponse,
} from '../types/notifications'

function toQuery(params: ListNotificationsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    if (typeof value === 'boolean') {
      if (value) {
        query.set(key, '1')
      }
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listNotifications(params: ListNotificationsParams = {}): Promise<{
  data: Notification[]
  meta: NotificationsListMeta
}> {
  const response = await apiGet<Notification[]>(`/api/v1/notifications${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as NotificationsListMeta,
  }
}

export async function getUnreadCount(): Promise<UnreadCountResponse> {
  const response = await apiGet<UnreadCountResponse>('/api/v1/notifications/unread-count')
  return response.data
}

export async function getNotification(id: number): Promise<Notification> {
  const response = await apiGet<Notification>(`/api/v1/notifications/${id}`)
  return response.data
}

export async function markNotificationRead(id: number): Promise<Notification> {
  const response = await apiPost<Notification>(`/api/v1/notifications/${id}/read`, {})
  return response.data
}

export async function markAllNotificationsRead(): Promise<{ updated_count: number }> {
  const response = await apiPost<{ updated_count: number }>(
    '/api/v1/notifications/read-all',
    {},
  )
  return response.data
}
