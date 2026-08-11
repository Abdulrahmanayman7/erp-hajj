import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listDocumentCategories } from '../api/categoriesApi'
import type { ListDocumentCategoriesParams } from '../types/categories'

export const documentCategoriesQueryKey = ['document-categories'] as const

export function useDocumentCategoriesQuery(
  params: MaybeRefOrGetter<ListDocumentCategoriesParams> = {},
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) {
  return useQuery({
    queryKey: computed(() => [...documentCategoriesQueryKey, toValue(params)]),
    queryFn: () => listDocumentCategories(toValue(params)),
    enabled: computed(() => (options?.enabled === undefined ? true : toValue(options.enabled))),
  })
}
