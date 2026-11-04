import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import { getRfq, listRfqs } from '../api/rfqApi'

export function useRfqsQuery(filters: MaybeRefOrGetter<{
  status?: string
  purchase_request_id?: number
  q?: string
  page?: number
}>) {
  return useQuery({
    queryKey: computed(() => ['rfqs', toValue(filters)]),
    queryFn: () => listRfqs(toValue(filters)),
  })
}

export function useRfqQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => ['rfq', toValue(id)]),
    enabled: computed(() => toValue(id) != null && Number(toValue(id)) > 0),
    queryFn: () => getRfq(Number(toValue(id))),
  })
}
