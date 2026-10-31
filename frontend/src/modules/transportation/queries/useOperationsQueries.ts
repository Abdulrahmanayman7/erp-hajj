import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getMovement, listMovements } from '../api/movementsApi'
import { getRun, listRunPassengers } from '../api/runsApi'
import type { ListMovementsParams, PassengerStatus } from '../types/operations'

export const transportMovementsQueryKey = ['transport-movements'] as const
export const transportRunsQueryKey = ['transport-runs'] as const

export function useMovementsQuery(
  params: MaybeRefOrGetter<ListMovementsParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...transportMovementsQueryKey, 'list', toValue(params)]),
    queryFn: () => listMovements(toValue(params)),
    enabled: computed(() => toValue(enabled)),
    placeholderData: keepPreviousData,
  })
}

export function useMovementQuery(
  id: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...transportMovementsQueryKey, 'detail', toValue(id), toValue(seasonId)]),
    queryFn: () => getMovement(toValue(id) as number, toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(id) != null),
  })
}

export function useRunQuery(
  id: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...transportRunsQueryKey, 'detail', toValue(id), toValue(seasonId)]),
    queryFn: () => getRun(toValue(id) as number, toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(id) != null),
  })
}

export function useRunPassengersQuery(
  runId: MaybeRefOrGetter<number | null>,
  params: MaybeRefOrGetter<{ status?: PassengerStatus | ''; page?: number; per_page?: number }>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [
      ...transportRunsQueryKey,
      'passengers',
      toValue(runId),
      toValue(params),
      toValue(seasonId),
    ]),
    queryFn: () => listRunPassengers(toValue(runId) as number, toValue(params), toValue(seasonId)),
    enabled: computed(() => toValue(enabled) && toValue(runId) != null),
    placeholderData: keepPreviousData,
  })
}
