import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listSupplierCategories } from '../api/categoriesApi'
import type { ListCategoriesParams } from '../types/categories'

export const supplierCategoriesQueryKey = ['supplier-categories'] as const

export function useSupplierCategoriesQuery(
  params: MaybeRefOrGetter<ListCategoriesParams>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) {
  return useQuery({
    queryKey: computed(() => [...supplierCategoriesQueryKey, 'list', toValue(params)]),
    queryFn: () => listSupplierCategories(toValue(params)),
    enabled: computed(() => (options?.enabled != null ? toValue(options.enabled) : true)),
  })
}
