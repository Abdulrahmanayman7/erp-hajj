import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getInventoryItem, listInventoryItems, listItemBalances } from '../api/itemsApi'
import type { ListInventoryItemsParams } from '../types/items'

export const inventoryItemsQueryKey = ['inventory-items'] as const
export const inventoryItemDetailQueryKey = (id: number) =>
  [...inventoryItemsQueryKey, 'detail', id] as const
export const inventoryItemBalancesQueryKey = (id: number) =>
  [...inventoryItemsQueryKey, 'balances', id] as const

export function useInventoryItemsQuery(params: MaybeRefOrGetter<ListInventoryItemsParams>) {
  return useQuery({
    queryKey: computed(() => [...inventoryItemsQueryKey, 'list', toValue(params)]),
    queryFn: () => listInventoryItems(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useInventoryItemQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...inventoryItemsQueryKey, 'detail', 'unknown']
        : inventoryItemDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getInventoryItem(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}

export function useItemBalancesQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...inventoryItemsQueryKey, 'balances', 'unknown']
        : inventoryItemBalancesQueryKey(toValue(id)!),
    ),
    queryFn: () => listItemBalances(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
