import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listPositions } from '../api/positionsApi'
import type { ListPositionsParams } from '../types/positions'

export const positionsQueryKey = ['positions'] as const

export function usePositionsQuery(
  params: MaybeRefOrGetter<ListPositionsParams> = {},
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) {
  return useQuery({
    queryKey: computed(() => [...positionsQueryKey, toValue(params)]),
    queryFn: () => listPositions(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => (options?.enabled === undefined ? true : toValue(options.enabled))),
  })
}
