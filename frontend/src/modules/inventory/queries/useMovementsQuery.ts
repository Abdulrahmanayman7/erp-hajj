import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getInventoryMovement, listInventoryMovements } from '../api/movementsApi'
import type { ListInventoryMovementsParams } from '../types/movements'

export const inventoryMovementsQueryKey = ['inventory-movements'] as const
export const inventoryMovementDetailQueryKey = (id: number) =>
  [...inventoryMovementsQueryKey, 'detail', id] as const

export function useInventoryMovementsQuery(params: MaybeRefOrGetter<ListInventoryMovementsParams>) {
  return useQuery({
    queryKey: computed(() => [...inventoryMovementsQueryKey, 'list', toValue(params)]),
    queryFn: () => listInventoryMovements(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useInventoryMovementQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...inventoryMovementsQueryKey, 'detail', 'unknown']
        : inventoryMovementDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getInventoryMovement(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
