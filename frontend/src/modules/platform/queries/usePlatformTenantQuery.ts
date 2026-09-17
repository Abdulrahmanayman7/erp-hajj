import { useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getPlatformTenant } from '../api/platformTenantsApi'
import { platformTenantsQueryKey } from './usePlatformTenantsQuery'

export function platformTenantQueryKey(id: number) {
  return [...platformTenantsQueryKey, 'detail', id] as const
}

export function usePlatformTenantQuery(id: MaybeRefOrGetter<number>) {
  return useQuery({
    queryKey: computed(() => platformTenantQueryKey(toValue(id))),
    queryFn: () => getPlatformTenant(toValue(id)),
    enabled: computed(() => Number.isFinite(toValue(id)) && toValue(id) > 0),
  })
}
