import { useQuery } from '@tanstack/vue-query'

import { fetchOrganizationTree } from '../api/organizationTreeApi'

export const organizationTreeQueryKey = ['organization-tree'] as const

export function useOrganizationTreeQuery() {
  return useQuery({
    queryKey: organizationTreeQueryKey,
    queryFn: fetchOrganizationTree,
    staleTime: 60 * 1000,
  })
}
