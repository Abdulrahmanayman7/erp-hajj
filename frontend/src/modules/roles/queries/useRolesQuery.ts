import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getRole, listPermissionCatalog, listRoles, type ListRolesParams } from '../api/rolesApi'

export const rolesQueryKey = ['roles'] as const
export const permissionsCatalogQueryKey = ['permissions', 'catalog'] as const

export function useRolesQuery(params: MaybeRefOrGetter<ListRolesParams>) {
  return useQuery({
    queryKey: computed(() => [...rolesQueryKey, toValue(params)]),
    queryFn: () => listRoles(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useRoleQuery(id: MaybeRefOrGetter<number>) {
  return useQuery({
    queryKey: computed(() => [...rolesQueryKey, 'detail', toValue(id)]),
    queryFn: () => getRole(toValue(id)),
  })
}

export function usePermissionsCatalogQuery(search: MaybeRefOrGetter<string> = '') {
  return useQuery({
    queryKey: computed(() => [...permissionsCatalogQueryKey, toValue(search)]),
    queryFn: () => listPermissionCatalog(toValue(search) || undefined),
  })
}
