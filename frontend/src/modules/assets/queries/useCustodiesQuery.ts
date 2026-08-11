import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getAssetCustody, listAssetCustodies, listMyCustodies } from '../api/custodiesApi'
import type { ListCustodiesParams } from '../types/custodies'

export const assetCustodiesQueryKey = ['asset-custodies'] as const
export const myCustodiesQueryKey = ['my-custodies'] as const
export const assetCustodyDetailQueryKey = (id: number) =>
  [...assetCustodiesQueryKey, 'detail', id] as const

export function useAssetCustodiesQuery(params: MaybeRefOrGetter<ListCustodiesParams>) {
  return useQuery({
    queryKey: computed(() => [...assetCustodiesQueryKey, 'list', toValue(params)]),
    queryFn: () => listAssetCustodies(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useMyCustodiesQuery(params: MaybeRefOrGetter<ListCustodiesParams> = {}) {
  return useQuery({
    queryKey: computed(() => [...myCustodiesQueryKey, 'list', toValue(params)]),
    queryFn: () => listMyCustodies(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useAssetCustodyQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...assetCustodiesQueryKey, 'detail', 'unknown']
        : assetCustodyDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getAssetCustody(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
