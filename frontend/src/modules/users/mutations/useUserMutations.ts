import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { currentUserQueryKey } from '@/modules/auth/queries/useCurrentUserQuery'

import {
  createUser,
  disableUser,
  enableUser,
  syncUserRoles,
  updateUser,
} from '../api/usersApi'
import type { CreateUserPayload, UpdateUserPayload } from '../types/users'
import { usersQueryKey } from '../queries/useUsersQuery'

export function useCreateUserMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateUserPayload) => createUser(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey })
    },
  })
}

export function useUpdateUserMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateUserPayload }) =>
      updateUser(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey })
    },
  })
}

export function useDisableUserMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => disableUser(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey })
      await queryClient.invalidateQueries({ queryKey: currentUserQueryKey })
    },
  })
}

export function useEnableUserMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => enableUser(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey })
    },
  })
}

export function useSyncUserRolesMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, roleIds }: { id: number; roleIds: number[] }) => syncUserRoles(id, roleIds),
    onSuccess: async (_data, variables) => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey })
      const me = queryClient.getQueryData(currentUserQueryKey) as { id?: number } | null
      if (me?.id === variables.id) {
        await queryClient.invalidateQueries({ queryKey: currentUserQueryKey })
      }
    },
  })
}
