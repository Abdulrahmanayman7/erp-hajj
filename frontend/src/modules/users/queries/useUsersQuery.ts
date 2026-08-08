import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listUsers, type ListUsersParams } from '../api/usersApi'

export const usersQueryKey = ['users'] as const

export function useUsersQuery(params: MaybeRefOrGetter<ListUsersParams>) {
  return useQuery({
    queryKey: computed(() => [...usersQueryKey, toValue(params)]),
    queryFn: () => listUsers(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
