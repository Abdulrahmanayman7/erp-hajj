import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listSeasons } from '../api/seasonsApi'
import type { ListSeasonsParams } from '../types/seasons'

export const seasonsQueryKey = ['seasons'] as const

export function useSeasonsQuery(params: MaybeRefOrGetter<ListSeasonsParams>) {
  return useQuery({
    queryKey: computed(() => [...seasonsQueryKey, toValue(params)]),
    queryFn: () => listSeasons(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
