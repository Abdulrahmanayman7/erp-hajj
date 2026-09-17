import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listPlatformTenants } from '../api/platformTenantsApi'
import type { ListPlatformTenantsParams } from '../types/platform'

export const platformTenantsQueryKey = ['platform', 'tenants'] as const

export function usePlatformTenantsQuery(params: MaybeRefOrGetter<ListPlatformTenantsParams>) {
  return useQuery({
    queryKey: computed(() => [...platformTenantsQueryKey, toValue(params)]),
    queryFn: () => listPlatformTenants(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
