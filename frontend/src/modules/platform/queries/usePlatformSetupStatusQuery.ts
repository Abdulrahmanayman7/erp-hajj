import { useQuery } from '@tanstack/vue-query'

import { fetchPlatformSetupStatus } from '../api/platformSetupApi'

export const platformSetupStatusQueryKey = ['platform', 'setup', 'status'] as const

export function usePlatformSetupStatusQuery(enabled = true) {
  return useQuery({
    queryKey: platformSetupStatusQueryKey,
    queryFn: fetchPlatformSetupStatus,
    enabled,
    staleTime: 60 * 1000,
    retry: false,
  })
}
