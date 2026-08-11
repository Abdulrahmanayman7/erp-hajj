import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getWarehouse, listWarehouses } from '../api/warehousesApi'
import type { ListWarehousesParams } from '../types/warehouses'

export const warehousesQueryKey = ['warehouses'] as const
export const warehouseDetailQueryKey = (id: number) =>
  [...warehousesQueryKey, 'detail', id] as const

export function useWarehousesQuery(params: MaybeRefOrGetter<ListWarehousesParams>) {
  return useQuery({
    queryKey: computed(() => [...warehousesQueryKey, 'list', toValue(params)]),
    queryFn: () => listWarehouses(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useWarehouseQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...warehousesQueryKey, 'detail', 'unknown']
        : warehouseDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getWarehouse(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
