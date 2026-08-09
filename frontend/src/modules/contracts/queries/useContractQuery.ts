import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getContract } from '../api/contractsApi'
import { contractsQueryKey } from './useContractsQuery'

export function contractDetailQueryKey(id: number) {
  return [...contractsQueryKey, 'detail', id] as const
}

export function useContractQuery(
  id: MaybeRefOrGetter<number | null | undefined>,
  options?: { enabled?: MaybeRefOrGetter<boolean> },
) {
  return useQuery({
    queryKey: computed(() => {
      const value = toValue(id)
      return value == null ? [...contractsQueryKey, 'detail', 'unknown'] : contractDetailQueryKey(value)
    }),
    queryFn: () => {
      const value = toValue(id)
      if (value == null) {
        throw new Error('Contract id is required')
      }
      return getContract(value)
    },
    enabled: computed(() => {
      const value = toValue(id)
      const enabled = options?.enabled === undefined ? true : toValue(options.enabled)
      return enabled && value != null && Number.isFinite(value) && value > 0
    }),
  })
}
