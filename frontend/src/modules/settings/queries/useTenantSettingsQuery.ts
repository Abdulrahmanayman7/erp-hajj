import { useQuery } from '@tanstack/vue-query'
import { computed, toValue, type MaybeRefOrGetter } from 'vue'

import { getTenantSettings } from '../api/settingsApi'

export const tenantSettingsQueryKey = ['tenant-settings'] as const

export function useTenantSettingsQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: tenantSettingsQueryKey,
    queryFn: getTenantSettings,
    enabled: computed(() => toValue(enabled)),
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  })
}
