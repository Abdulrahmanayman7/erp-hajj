import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  approvePurchaseRequest,
  cancelApprovedPurchaseRequest,
  cancelPurchaseRequest,
  createPurchaseApprovalMatrix,
  createPurchaseRequest,
  grantPurchaseApproverAuthority,
  publishPurchaseApprovalMatrix,
  recordFinancialReview,
  rejectPurchaseRequest,
  replacePurchaseRequestLines,
  returnPurchaseRequest,
  submitPurchaseRequest,
  updatePurchaseRequest,
  upsertPurchaseApprovalSlaPolicy,
  verifyPurchaseRequestPriority,
} from '../api/purchaseRequestsApi'
import type {
  CreatePurchaseRequestPayload,
  UpdatePurchaseRequestPayload,
} from '../types/purchaseRequests'

export function useCreatePurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreatePurchaseRequestPayload) => createPurchaseRequest(payload),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ['purchase-requests'] })
    },
  })
}

export function useUpdatePurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePurchaseRequestPayload }) =>
      updatePurchaseRequest(id, payload),
    onSuccess: async (pr) => {
      await client.invalidateQueries({ queryKey: ['purchase-requests'] })
      await client.invalidateQueries({ queryKey: ['purchase-request', pr.id] })
    },
  })
}

export function useReplacePurchaseRequestLinesMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      lines,
    }: {
      id: number
      lines: CreatePurchaseRequestPayload['lines']
    }) => replacePurchaseRequestLines(id, lines),
    onSuccess: async (pr) => {
      await client.invalidateQueries({ queryKey: ['purchase-request', pr.id] })
    },
  })
}

export function useSubmitPurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      acknowledgeDuplicateWarning,
    }: {
      id: number
      acknowledgeDuplicateWarning?: boolean
    }) => submitPurchaseRequest(id, acknowledgeDuplicateWarning ?? false),
    onSuccess: async (pr) => {
      await client.invalidateQueries({ queryKey: ['purchase-requests'] })
      await client.invalidateQueries({ queryKey: ['purchase-request', pr.id] })
      await client.invalidateQueries({ queryKey: ['purchase-request-versions', pr.id] })
    },
  })
}

export function useCancelPurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => cancelPurchaseRequest(id, reason),
    onSuccess: async (pr) => {
      await client.invalidateQueries({ queryKey: ['purchase-requests'] })
      await client.invalidateQueries({ queryKey: ['purchase-request', pr.id] })
    },
  })
}

export function useVerifyPriorityMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => verifyPurchaseRequestPriority(id),
    onSuccess: async (pr) => {
      await client.invalidateQueries({ queryKey: ['purchase-request', pr.id] })
    },
  })
}

export function useRecordFinancialReviewMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      outcome,
      justification,
      version_no,
    }: {
      id: number
      outcome: string
      justification: string
      version_no?: number
    }) => recordFinancialReview(id, { outcome, justification, version_no }),
    onSuccess: async (_review, vars) => {
      await client.invalidateQueries({ queryKey: ['purchase-request', vars.id] })
      await client.invalidateQueries({
        queryKey: ['purchase-request-financial-reviews', vars.id],
      })
    },
  })
}

async function invalidatePr(client: ReturnType<typeof useQueryClient>, id: number) {
  await client.invalidateQueries({ queryKey: ['purchase-requests'] })
  await client.invalidateQueries({ queryKey: ['purchase-request', id] })
  await client.invalidateQueries({ queryKey: ['purchase-request-approval-inbox'] })
}

export function useApprovePurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, comment }: { id: number; comment?: string }) =>
      approvePurchaseRequest(id, comment),
    onSuccess: async (pr) => {
      await invalidatePr(client, pr.id)
    },
  })
}

export function useRejectPurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      rejectPurchaseRequest(id, reason),
    onSuccess: async (pr) => {
      await invalidatePr(client, pr.id)
    },
  })
}

export function useReturnPurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      returnPurchaseRequest(id, reason),
    onSuccess: async (pr) => {
      await invalidatePr(client, pr.id)
    },
  })
}

export function useCancelApprovedPurchaseRequestMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) =>
      cancelApprovedPurchaseRequest(id, reason),
    onSuccess: async (pr) => {
      await invalidatePr(client, pr.id)
    },
  })
}

export function useCreatePurchaseApprovalMatrixMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (name: string) => createPurchaseApprovalMatrix(name),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ['purchase-approval-matrices'] })
    },
  })
}

export function usePublishPurchaseApprovalMatrixMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => publishPurchaseApprovalMatrix(id),
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ['purchase-approval-matrices'] })
    },
  })
}

export function useGrantPurchaseApproverAuthorityMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: grantPurchaseApproverAuthority,
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ['purchase-approver-authorities'] })
    },
  })
}

export function useUpsertPurchaseApprovalSlaPolicyMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: upsertPurchaseApprovalSlaPolicy,
    onSuccess: async () => {
      await client.invalidateQueries({ queryKey: ['purchase-approval-sla-policies'] })
    },
  })
}
