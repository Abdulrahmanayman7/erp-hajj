import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'

import {
  activateRole,
  createRole,
  deactivateRole,
  deleteRole,
  syncRolePermissions,
  updateRole,
} from '../api/rolesApi'
import type { CreateRolePayload, UpdateRolePayload } from '../types/roles'
import { rolesQueryKey } from '../queries/useRolesQuery'

export function useCreateRoleMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateRolePayload) => createRole(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesQueryKey })
    },
  })
}

export function useUpdateRoleMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateRolePayload }) =>
      updateRole(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesQueryKey })
    },
  })
}

export function useActivateRoleMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateRole(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesQueryKey })
      await queryClient.invalidateQueries({ queryKey: currentUserQueryKey })
    },
  })
}

export function useDeactivateRoleMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateRole(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesQueryKey })
      await queryClient.invalidateQueries({ queryKey: currentUserQueryKey })
    },
  })
}

export function useDeleteRoleMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteRole(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesQueryKey })
    },
  })
}

export function useSyncRolePermissionsMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, permissionIds }: { id: number; permissionIds: number[] }) =>
      syncRolePermissions(id, permissionIds),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: rolesQueryKey })
      await queryClient.invalidateQueries({ queryKey: currentUserQueryKey })
    },
  })
}
