import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listSuppliers } from '../api/suppliersApi'
import type { ListSuppliersParams } from '../types/suppliers'

export const suppliersQueryKey = ['suppliers'] as const

export function useSuppliersQuery(params: MaybeRefOrGetter<ListSuppliersParams>) {
  return useQuery({
    queryKey: computed(() => [...suppliersQueryKey, 'list', toValue(params)]),
    queryFn: () => listSuppliers(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
