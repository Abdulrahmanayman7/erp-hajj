import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { acknowledgeExecutiveAlert, listExecutiveAlerts } from '../api/executiveAlertsApi'

export function executiveAlertsQueryKey(seasonId: number | null) {
  return ['executive-alerts', seasonId] as const
}

export function useExecutiveAlertsQuery(
  seasonId: MaybeRefOrGetter<number | null>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => executiveAlertsQueryKey(toValue(seasonId))),
    queryFn: () => {
      const id = toValue(seasonId)
      if (id == null) {
        throw new Error('season_id required')
      }
      return listExecutiveAlerts(id)
    },
    enabled: computed(() => toValue(enabled) && toValue(seasonId) != null),
    staleTime: 0,
    retry: false,
  })
}

export function useAcknowledgeExecutiveAlertMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (alertId: number) => acknowledgeExecutiveAlert(alertId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['executive-alerts'] }),
        queryClient.invalidateQueries({ queryKey: ['executive-dashboard'] }),
      ])
    },
  })
}
