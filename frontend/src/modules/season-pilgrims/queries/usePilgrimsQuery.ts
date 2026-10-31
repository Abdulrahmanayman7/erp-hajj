import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getPilgrim, listPilgrims } from '../api/pilgrimsApi'
import { listSeasonPilgrims } from '../api/seasonPilgrimsApi'
import type { ListPilgrimsParams } from '../types/pilgrims'
import type { ListSeasonPilgrimsParams } from '../types/seasonPilgrims'

export const pilgrimsQueryKey = ['pilgrims'] as const
export const seasonPilgrimsQueryKey = ['season-pilgrims'] as const

export function usePilgrimsQuery(params: MaybeRefOrGetter<ListPilgrimsParams>) {
  return useQuery({
    queryKey: computed(() => [...pilgrimsQueryKey, toValue(params)]),
    queryFn: () => listPilgrims(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function usePilgrimQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => [...pilgrimsQueryKey, 'detail', toValue(id)]),
    queryFn: () => getPilgrim(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && value > 0
    }),
  })
}

export function useSeasonPilgrimsQuery(
  params: MaybeRefOrGetter<ListSeasonPilgrimsParams & { seasonId?: number | null }>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => {
      const value = toValue(params)
      return [...seasonPilgrimsQueryKey, value]
    }),
    queryFn: () => {
      const value = toValue(params)
      const { seasonId, ...filters } = value
      return listSeasonPilgrims(filters, seasonId)
    },
    enabled: computed(() => Boolean(toValue(enabled))),
    placeholderData: keepPreviousData,
  })
}
