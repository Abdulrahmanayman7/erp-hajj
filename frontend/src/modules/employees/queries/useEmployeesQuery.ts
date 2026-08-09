import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listEmployees } from '../api/employeesApi'
import type { ListEmployeesParams } from '../types/employees'

export const employeesQueryKey = ['employees'] as const

export function useEmployeesQuery(params: MaybeRefOrGetter<ListEmployeesParams>) {
  return useQuery({
    queryKey: computed(() => [...employeesQueryKey, toValue(params)]),
    queryFn: () => listEmployees(toValue(params)),
    placeholderData: keepPreviousData,
  })
}
