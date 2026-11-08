import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getSeasonCopyRun, listSeasonCopyRuns } from '../api/seasonCopyApi'
import type { ListSeasonCopyRunsParams } from '../types/seasonCopy'

export const seasonCopyRunsQueryKey = ['season-copy-runs'] as const
export const seasonCopyRunDetailQueryKey = (id: number) =>
  [...seasonCopyRunsQueryKey, 'detail', id] as const

export function useSeasonCopyRunsQuery(
  params: MaybeRefOrGetter<ListSeasonCopyRunsParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...seasonCopyRunsQueryKey, 'list', toValue(params)]),
    queryFn: () => listSeasonCopyRuns(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => toValue(enabled)),
  })
}

export function useSeasonCopyRunQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...seasonCopyRunsQueryKey, 'detail', 'unknown']
        : seasonCopyRunDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getSeasonCopyRun(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
