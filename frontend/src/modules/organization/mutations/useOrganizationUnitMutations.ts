import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateOrganizationUnit,
  createOrganizationUnit,
  deactivateOrganizationUnit,
  deleteOrganizationUnit,
  moveOrganizationUnit,
  updateOrganizationUnit,
} from '../api/organizationUnitsApi'
import type {
  CreateOrganizationUnitPayload,
  UpdateOrganizationUnitPayload,
} from '../types/organization'
import { organizationUnitsQueryKey } from '../queries/useOrganizationUnitsQuery'

async function invalidateOrganization(queryClient: ReturnType<typeof useQueryClient>) {
  await queryClient.invalidateQueries({ queryKey: organizationUnitsQueryKey })
}

export function useCreateOrganizationUnitMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateOrganizationUnitPayload) => createOrganizationUnit(payload),
    onSuccess: async () => invalidateOrganization(queryClient),
  })
}

export function useUpdateOrganizationUnitMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateOrganizationUnitPayload }) =>
      updateOrganizationUnit(id, payload),
    onSuccess: async () => invalidateOrganization(queryClient),
  })
}

export function useMoveOrganizationUnitMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, parentId }: { id: number; parentId: number | null }) =>
      moveOrganizationUnit(id, parentId),
    onSuccess: async () => invalidateOrganization(queryClient),
  })
}

export function useActivateOrganizationUnitMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateOrganizationUnit(id),
    onSuccess: async () => invalidateOrganization(queryClient),
  })
}

export function useDeactivateOrganizationUnitMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateOrganizationUnit(id),
    onSuccess: async () => invalidateOrganization(queryClient),
  })
}

export function useDeleteOrganizationUnitMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteOrganizationUnit(id),
    onSuccess: async () => invalidateOrganization(queryClient),
  })
}
