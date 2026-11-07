import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getRisk, listRisks } from '../api/risksApi'
import type { ListRisksParams } from '../types/risks'

export const risksQueryKey = ['risks'] as const
export const riskDetailQueryKey = (id: number) => [...risksQueryKey, 'detail', id] as const

export function useRisksQuery(
  params: MaybeRefOrGetter<ListRisksParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...risksQueryKey, 'list', toValue(params)]),
    queryFn: () => listRisks(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => toValue(enabled)),
  })
}

export function useRiskQuery(
  id: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...risksQueryKey, 'detail', 'unknown']
        : [...riskDetailQueryKey(toValue(id)!), toValue(seasonId) ?? null],
    ),
    queryFn: () => getRisk(toValue(id)!, toValue(seasonId)),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
