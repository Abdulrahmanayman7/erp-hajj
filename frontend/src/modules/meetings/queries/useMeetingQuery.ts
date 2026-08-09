import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getMeeting } from '../api/meetingsApi'
import { meetingsQueryKey } from './useMeetingsQuery'

export function meetingDetailQueryKey(id: number) {
  return [...meetingsQueryKey, 'detail', id] as const
}

export function useMeetingQuery(
  id: MaybeRefOrGetter<number | null | undefined>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) {
  return useQuery({
    queryKey: computed(() => {
      const value = toValue(id)
      return value == null
        ? [...meetingsQueryKey, 'detail', 'unknown']
        : meetingDetailQueryKey(value)
    }),
    queryFn: () => {
      const value = toValue(id)
      if (value == null) {
        throw new Error('Meeting id is required')
      }
      return getMeeting(value)
    },
    enabled: computed(() => {
      const value = toValue(id)
      const enabled = options?.enabled === undefined ? true : toValue(options.enabled)
      return enabled && value != null && Number.isFinite(value) && value > 0
    }),
  })
}
