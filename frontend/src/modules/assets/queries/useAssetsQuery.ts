import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getAsset, listAssets } from '../api/assetsApi'
import type { ListAssetsParams } from '../types/assets'

export const assetsQueryKey = ['assets'] as const
export const assetDetailQueryKey = (id: number) => [...assetsQueryKey, 'detail', id] as const

export function useAssetsQuery(params: MaybeRefOrGetter<ListAssetsParams>) {
  return useQuery({
    queryKey: computed(() => [...assetsQueryKey, 'list', toValue(params)]),
    queryFn: () => listAssets(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useAssetQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...assetsQueryKey, 'detail', 'unknown']
        : assetDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getAsset(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
