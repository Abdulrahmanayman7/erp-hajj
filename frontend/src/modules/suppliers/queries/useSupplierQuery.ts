import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getSupplier } from '../api/suppliersApi'

export function supplierDetailQueryKey(id: number) {
  return ['suppliers', 'detail', id] as const
}

export function useSupplierQuery(supplierId: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => {
      const id = toValue(supplierId)
      return id != null ? supplierDetailQueryKey(id) : ['suppliers', 'detail', 'none']
    }),
    queryFn: () => {
      const id = toValue(supplierId)
      if (id == null) throw new Error('missing supplier id')
      return getSupplier(id)
    },
    enabled: computed(() => toValue(supplierId) != null),
  })
}
