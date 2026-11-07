import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getComplaint, listComplaints } from '../api/complaintsApi'
import type { ListComplaintsParams } from '../types/complaints'

export const complaintsQueryKey = ['complaints'] as const
export const complaintDetailQueryKey = (id: number) =>
  [...complaintsQueryKey, 'detail', id] as const

export function useComplaintsQuery(
  params: MaybeRefOrGetter<ListComplaintsParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...complaintsQueryKey, 'list', toValue(params)]),
    queryFn: () => listComplaints(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => toValue(enabled)),
  })
}

export function useComplaintQuery(
  id: MaybeRefOrGetter<number | null>,
  seasonId: MaybeRefOrGetter<number | null | undefined> = null,
) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...complaintsQueryKey, 'detail', 'unknown']
        : [...complaintDetailQueryKey(toValue(id)!), toValue(seasonId) ?? null],
    ),
    queryFn: () => getComplaint(toValue(id)!, toValue(seasonId)),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
