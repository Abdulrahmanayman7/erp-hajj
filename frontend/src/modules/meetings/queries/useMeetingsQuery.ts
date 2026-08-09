import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listMeetings } from '../api/meetingsApi'
import type { ListMeetingsParams } from '../types/meetings'

export const meetingsQueryKey = ['meetings'] as const

export function useMeetingsQuery(params: MaybeRefOrGetter<ListMeetingsParams>) {
  return useQuery({
    queryKey: computed(() => [...meetingsQueryKey, 'list', toValue(params)]),
    queryFn: () => listMeetings(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
