import { computed, type MaybeRefOrGetter, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import { listPlatformTenantUsers } from '../api/platformTenantsApi'

export function platformTenantUsersQueryKey(id: number) {
  return ['platform', 'tenants', id, 'users'] as const
}

export function usePlatformTenantUsersQuery(id: MaybeRefOrGetter<number>) {
  return useQuery({
    queryKey: computed(() => platformTenantUsersQueryKey(toValue(id))),
    queryFn: () => listPlatformTenantUsers(toValue(id)),
    enabled: computed(() => Number.isFinite(toValue(id)) && toValue(id) > 0),
    staleTime: 30 * 1000,
  })
}
