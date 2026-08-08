import { computed } from 'vue'

import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'

export function usePermissions() {
  const { data: user } = useCurrentUserQuery()

  const permissions = computed(() => user.value?.permissions ?? [])

  function can(permission: string): boolean {
    return permissions.value.includes(permission)
  }

  function canAny(required: string[]): boolean {
    return required.some((permission) => permissions.value.includes(permission))
  }

  function canAll(required: string[]): boolean {
    return required.every((permission) => permissions.value.includes(permission))
  }

  function hasRole(code: string): boolean {
    return (user.value?.roles ?? []).some((role) => role.code === code)
  }

  return { permissions, can, canAny, canAll, hasRole }
}
