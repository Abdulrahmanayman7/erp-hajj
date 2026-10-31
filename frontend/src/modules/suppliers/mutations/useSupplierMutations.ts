import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  addSupplierCoverage,
  createSupplier,
  createSupplierContact,
  deleteSupplier,
  deleteSupplierContact,
  deleteSupplierCoverage,
  transitionSupplier,
  updateSupplier,
  updateSupplierContact,
} from '../api/suppliersApi'
import { supplierDetailQueryKey } from '../queries/useSupplierQuery'
import { suppliersQueryKey } from '../queries/useSuppliersQuery'
import type {
  AddSupplierCoveragePayload,
  CreateSupplierContactPayload,
  CreateSupplierPayload,
  SupplierTransitionPayload,
  UpdateSupplierContactPayload,
  UpdateSupplierPayload,
} from '../types/suppliers'

async function invalidateSupplierQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: suppliersQueryKey })
  if (id != null) {
    await queryClient.invalidateQueries({ queryKey: supplierDetailQueryKey(id) })
  }
}

export function useCreateSupplierMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateSupplierPayload) => createSupplier(payload),
    onSuccess: async () => {
      await invalidateSupplierQueries(queryClient)
    },
  })
}

export function useUpdateSupplierMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateSupplierPayload }) =>
      updateSupplier(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.id)
    },
  })
}

export function useDeleteSupplierMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteSupplier(id),
    onSuccess: async (_data, id) => {
      await invalidateSupplierQueries(queryClient, id)
    },
  })
}

export function useTransitionSupplierMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: SupplierTransitionPayload }) =>
      transitionSupplier(id, payload),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.id)
    },
  })
}

export function useCreateSupplierContactMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      payload,
    }: {
      supplierId: number
      payload: CreateSupplierContactPayload
    }) => createSupplierContact(supplierId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.supplierId)
    },
  })
}

export function useUpdateSupplierContactMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      contactId,
      payload,
    }: {
      supplierId: number
      contactId: number
      payload: UpdateSupplierContactPayload
    }) => updateSupplierContact(supplierId, contactId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.supplierId)
    },
  })
}

export function useDeleteSupplierContactMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ supplierId, contactId }: { supplierId: number; contactId: number }) =>
      deleteSupplierContact(supplierId, contactId),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.supplierId)
    },
  })
}

export function useAddSupplierCoverageMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      payload,
    }: {
      supplierId: number
      payload: AddSupplierCoveragePayload
    }) => addSupplierCoverage(supplierId, payload),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.supplierId)
    },
  })
}

export function useDeleteSupplierCoverageMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ supplierId, coverageId }: { supplierId: number; coverageId: number }) =>
      deleteSupplierCoverage(supplierId, coverageId),
    onSuccess: async (_data, variables) => {
      await invalidateSupplierQueries(queryClient, variables.supplierId)
    },
  })
}
