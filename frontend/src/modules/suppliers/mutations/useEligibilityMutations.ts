import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  createSupplierDocumentRecord,
  rejectSupplierDocumentRecord,
  supersedeSupplierDocumentRecord,
  verifySupplierDocumentRecord,
} from '../api/documentRecordsApi'
import {
  approveEligibilitySet,
  createEligibilitySet,
  newDraftFromEligibilitySet,
  updateEligibilitySet,
} from '../api/eligibilitySetsApi'
import {
  eligibilitySetsQueryKey,
  supplierDocumentRecordsQueryKey,
  supplierEligibilityQueryKey,
} from '../queries/useEligibilityQueries'
import type {
  CreateDocumentRecordPayload,
  CreateEligibilitySetPayload,
  SupersedeDocumentRecordPayload,
  UpdateEligibilitySetPayload,
} from '../types/eligibility'

type Client = ReturnType<typeof useQueryClient>

async function invalidateSets(client: Client): Promise<void> {
  await client.invalidateQueries({ queryKey: eligibilitySetsQueryKey })
  // Approved configs change eligibility evaluation of every supplier.
  await client.invalidateQueries({ queryKey: ['suppliers', 'eligibility'] })
}

async function invalidateRecords(client: Client, supplierId: number): Promise<void> {
  await client.invalidateQueries({ queryKey: supplierDocumentRecordsQueryKey(supplierId) })
  await client.invalidateQueries({ queryKey: supplierEligibilityQueryKey(supplierId) })
}

export function useCreateEligibilitySetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateEligibilitySetPayload) => createEligibilitySet(payload),
    onSuccess: () => invalidateSets(client),
  })
}

export function useUpdateEligibilitySetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateEligibilitySetPayload }) =>
      updateEligibilitySet(id, payload),
    onSuccess: () => invalidateSets(client),
  })
}

export function useApproveEligibilitySetMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, comment }: { id: number; comment?: string | null }) =>
      approveEligibilitySet(id, comment),
    onSuccess: () => invalidateSets(client),
  })
}

export function useNewEligibilityDraftMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => newDraftFromEligibilitySet(id),
    onSuccess: () => invalidateSets(client),
  })
}

export function useCreateDocumentRecordMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      payload,
    }: {
      supplierId: number
      payload: CreateDocumentRecordPayload
    }) => createSupplierDocumentRecord(supplierId, payload),
    onSuccess: (_data, variables) => invalidateRecords(client, variables.supplierId),
  })
}

export function useVerifyDocumentRecordMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      recordId,
      comment,
    }: {
      supplierId: number
      recordId: number
      comment?: string | null
    }) => verifySupplierDocumentRecord(supplierId, recordId, comment),
    onSuccess: (_data, variables) => invalidateRecords(client, variables.supplierId),
  })
}

export function useRejectDocumentRecordMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      recordId,
      rejectionReason,
    }: {
      supplierId: number
      recordId: number
      rejectionReason: string
    }) => rejectSupplierDocumentRecord(supplierId, recordId, rejectionReason),
    onSuccess: (_data, variables) => invalidateRecords(client, variables.supplierId),
  })
}

export function useSupersedeDocumentRecordMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      supplierId,
      recordId,
      payload,
    }: {
      supplierId: number
      recordId: number
      payload: SupersedeDocumentRecordPayload
    }) => supersedeSupplierDocumentRecord(supplierId, recordId, payload),
    onSuccess: (_data, variables) => invalidateRecords(client, variables.supplierId),
  })
}
