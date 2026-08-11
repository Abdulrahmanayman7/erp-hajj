import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listAssetCategories } from '../api/categoriesApi'
import type { ListAssetCategoriesParams } from '../types/categories'

export const assetCategoriesQueryKey = ['asset-categories'] as const

export function useAssetCategoriesQuery(
  params: MaybeRefOrGetter<ListAssetCategoriesParams> = {},
  options: { enabled?: MaybeRefOrGetter<boolean> } = {},
) {
  return useQuery({
    queryKey: computed(() => [...assetCategoriesQueryKey, 'list', toValue(params)]),
    queryFn: () => listAssetCategories(toValue(params)),
    enabled: computed(() => (options.enabled == null ? true : toValue(options.enabled))),
  })
}
