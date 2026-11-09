import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getExecutiveDashboard } from '../api/executiveDashboardApi'

export function executiveDashboardQueryKey(seasonId: number | null) {
  return ['executive-dashboard', seasonId] as const
}

export function useExecutiveDashboardQuery(
  seasonId: MaybeRefOrGetter<number | null>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => executiveDashboardQueryKey(toValue(seasonId))),
    queryFn: () => {
      const id = toValue(seasonId)
      if (id == null) {
        throw new Error('season_id required')
      }
      return getExecutiveDashboard(id)
    },
    enabled: computed(() => toValue(enabled) && toValue(seasonId) != null),
    staleTime: 0,
    retry: false,
  })
}
