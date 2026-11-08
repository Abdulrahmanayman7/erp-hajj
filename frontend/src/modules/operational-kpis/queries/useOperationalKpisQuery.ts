import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getOperationalKpis } from '../api/operationalKpisApi'

export const operationalKpisQueryKey = ['operational-kpis'] as const

export function useOperationalKpisQuery(
  seasonId: MaybeRefOrGetter<number | null>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...operationalKpisQueryKey, toValue(seasonId)]),
    queryFn: () => {
      const id = toValue(seasonId)
      if (id == null) {
        throw new Error('season_id required')
      }
      return getOperationalKpis(id)
    },
    enabled: computed(() => toValue(enabled) && toValue(seasonId) != null),
    staleTime: 0,
    gcTime: 0,
    refetchOnWindowFocus: false,
  })
}
