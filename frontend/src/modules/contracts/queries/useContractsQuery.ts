import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listContracts } from '../api/contractsApi'
import type { ListContractsParams } from '../types/contracts'

export const contractsQueryKey = ['contracts'] as const

export function useContractsQuery(params: MaybeRefOrGetter<ListContractsParams>) {
  return useQuery({
    queryKey: computed(() => [...contractsQueryKey, 'list', toValue(params)]),
    queryFn: () => listContracts(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
