import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import {
  getNotification,
  getUnreadCount,
  listNotifications,
} from '../api/notificationsApi'
import { BELL_RECENT_PER_PAGE, type ListNotificationsParams } from '../types/notifications'

export const notificationsQueryKey = ['notifications'] as const
export const unreadCountQueryKey = [...notificationsQueryKey, 'unread-count'] as const
export const notificationDetailQueryKey = (id: number) =>
  [...notificationsQueryKey, 'detail', id] as const

export function useUnreadCountQuery() {
  return useQuery({
    queryKey: unreadCountQueryKey,
    queryFn: getUnreadCount,
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  })
}

export function useNotificationsQuery(params: MaybeRefOrGetter<ListNotificationsParams>) {
  return useQuery({
    queryKey: computed(() => [...notificationsQueryKey, 'list', toValue(params)]),
    queryFn: () => listNotifications(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useRecentNotificationsQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  const params = computed<ListNotificationsParams>(() => ({
    page: 1,
    per_page: BELL_RECENT_PER_PAGE,
  }))

  return useQuery({
    queryKey: computed(() => [...notificationsQueryKey, 'recent', toValue(params)]),
    queryFn: () => listNotifications(toValue(params)),
    enabled: computed(() => toValue(enabled)),
    refetchInterval: computed(() => (toValue(enabled) ? 60_000 : false)),
    refetchOnWindowFocus: true,
  })
}

export function useNotificationQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...notificationsQueryKey, 'detail', 'unknown']
        : notificationDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getNotification(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
