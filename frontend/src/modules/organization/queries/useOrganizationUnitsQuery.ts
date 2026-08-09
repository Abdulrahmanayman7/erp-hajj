import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import {
  listOrganizationUnits,
  listOrganizationUnitsFlat,
} from '../api/organizationUnitsApi'
import type { ListOrganizationUnitsParams } from '../types/organization'

export const organizationUnitsQueryKey = ['organization-units'] as const

export function useOrganizationUnitsTreeQuery(
  params: MaybeRefOrGetter<ListOrganizationUnitsParams>,
) {
  return useQuery({
    queryKey: computed(() => [...organizationUnitsQueryKey, 'tree', toValue(params)]),
    queryFn: () => listOrganizationUnits({ view: 'tree', ...toValue(params) }),
    placeholderData: keepPreviousData,
  })
}

export function useOrganizationUnitsFlatQuery(
  params: MaybeRefOrGetter<ListOrganizationUnitsParams> = {},
) {
  return useQuery({
    queryKey: computed(() => [...organizationUnitsQueryKey, 'flat', toValue(params)]),
    queryFn: () => listOrganizationUnitsFlat(toValue(params)),
  })
}
