import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { markAllNotificationsRead, markNotificationRead } from '../api/notificationsApi'
import {
  notificationDetailQueryKey,
  notificationsQueryKey,
  unreadCountQueryKey,
} from '../queries/useNotificationsQuery'

async function invalidateNotificationQueries(
  client: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await Promise.all([
    client.invalidateQueries({ queryKey: notificationsQueryKey }),
    client.invalidateQueries({ queryKey: unreadCountQueryKey }),
  ])
  if (id) {
    await client.invalidateQueries({ queryKey: notificationDetailQueryKey(id) })
  }
}

export function useMarkNotificationReadMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => markNotificationRead(id),
    onSuccess: (_data, id) => invalidateNotificationQueries(client, id),
  })
}

export function useMarkAllNotificationsReadMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: () => invalidateNotificationQueries(client),
  })
}
