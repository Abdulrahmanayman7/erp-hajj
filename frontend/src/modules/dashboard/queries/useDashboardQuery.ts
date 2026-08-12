import { useQuery } from '@tanstack/vue-query'

import { getDashboard } from '../api/dashboardApi'

export const dashboardQueryKey = ['dashboard'] as const

export function useDashboardQuery() {
  return useQuery({
    queryKey: dashboardQueryKey,
    queryFn: getDashboard,
    refetchOnWindowFocus: true,
  })
}
