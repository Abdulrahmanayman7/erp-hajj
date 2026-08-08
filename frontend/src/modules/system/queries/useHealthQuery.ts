import { useQuery } from '@tanstack/vue-query'

import { fetchHealth } from '../api/health'

export function useHealthQuery() {
  return useQuery({
    queryKey: ['system', 'health'],
    queryFn: fetchHealth,
    retry: 1,
  })
}
