import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listInventoryCategories } from '../api/categoriesApi'
import type { ListInventoryCategoriesParams } from '../types/categories'

export const inventoryCategoriesQueryKey = ['inventory-categories'] as const

export function useInventoryCategoriesQuery(
  params: MaybeRefOrGetter<ListInventoryCategoriesParams> = {},
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => [...inventoryCategoriesQueryKey, 'list', toValue(params)]),
    queryFn: () => listInventoryCategories(toValue(params)),
    enabled: computed(() => (options.enabled == null ? true : toValue(options.enabled))),
  })
}
