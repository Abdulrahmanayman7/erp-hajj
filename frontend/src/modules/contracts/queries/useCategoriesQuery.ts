import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listCategories } from '../api/categoriesApi'
import type { ListCategoriesParams } from '../types/categories'

export const categoriesQueryKey = ['contract-categories'] as const

export function useCategoriesQuery(
  params: MaybeRefOrGetter<ListCategoriesParams> = {},
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) {
  return useQuery({
    queryKey: computed(() => [...categoriesQueryKey, toValue(params)]),
    queryFn: () => listCategories(toValue(params)),
    placeholderData: keepPreviousData,
    enabled: computed(() => (options?.enabled === undefined ? true : toValue(options.enabled))),
  })
}
