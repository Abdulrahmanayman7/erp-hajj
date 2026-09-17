import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activatePlatformTenant,
  archivePlatformTenant,
  createPlatformTenant,
  suspendPlatformTenant,
  transferPlatformTenantOwnership,
  updatePlatformTenant,
} from '../api/platformTenantsApi'
import type {
  ProvisionTenantPayload,
  UpdatePlatformTenantPayload,
} from '../types/platform'
import { platformTenantQueryKey } from '../queries/usePlatformTenantQuery'
import { platformTenantsQueryKey } from '../queries/usePlatformTenantsQuery'

function invalidateTenantQueries(queryClient: ReturnType<typeof useQueryClient>, id?: number) {
  void queryClient.invalidateQueries({ queryKey: platformTenantsQueryKey })
  if (id != null) {
    void queryClient.invalidateQueries({ queryKey: platformTenantQueryKey(id) })
  }
}

export function useCreatePlatformTenantMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ProvisionTenantPayload) => createPlatformTenant(payload),
    onSuccess: () => {
      invalidateTenantQueries(queryClient)
    },
  })
}

export function useUpdatePlatformTenantMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePlatformTenantPayload }) =>
      updatePlatformTenant(id, payload),
    onSuccess: (tenant) => {
      invalidateTenantQueries(queryClient, tenant.id)
    },
  })
}

export function useActivatePlatformTenantMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => activatePlatformTenant(id),
    onSuccess: (tenant) => {
      invalidateTenantQueries(queryClient, tenant.id)
    },
  })
}

export function useSuspendPlatformTenantMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      suspendPlatformTenant(id, reason),
    onSuccess: (tenant) => {
      invalidateTenantQueries(queryClient, tenant.id)
    },
  })
}

export function useArchivePlatformTenantMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      archivePlatformTenant(id, reason),
    onSuccess: (tenant) => {
      invalidateTenantQueries(queryClient, tenant.id)
    },
  })
}

export function useTransferPlatformTenantOwnershipMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, newOwnerId }: { id: number; newOwnerId: number }) =>
      transferPlatformTenantOwnership(id, newOwnerId),
    onSuccess: (tenant) => {
      invalidateTenantQueries(queryClient, tenant.id)
    },
  })
}
