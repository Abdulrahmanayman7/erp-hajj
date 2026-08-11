import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listInventoryBalances } from '../api/balancesApi'
import type { ListInventoryBalancesParams } from '../types/balances'

export const inventoryBalancesQueryKey = ['inventory-balances'] as const

export function useInventoryBalancesQuery(params: MaybeRefOrGetter<ListInventoryBalancesParams>) {
  return useQuery({
    queryKey: computed(() => [...inventoryBalancesQueryKey, 'list', toValue(params)]),
    queryFn: () => listInventoryBalances(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
