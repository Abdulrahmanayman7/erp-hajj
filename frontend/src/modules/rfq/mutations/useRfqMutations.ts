import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  cancelRfq,
  createRfq,
  createRfqOffer,
  inviteSupplierToRfq,
  publishRfq,
  selectRfqSupplier,
  submitOffer,
  withdrawOffer,
} from '../api/rfqApi'
import type { CreateOfferPayload, CreateRfqPayload, SelectSupplierPayload } from '../types/rfq'

function invalidateRfq(qc: ReturnType<typeof useQueryClient>, rfqId?: number): void {
  void qc.invalidateQueries({ queryKey: ['rfqs'] })
  if (rfqId != null) {
    void qc.invalidateQueries({ queryKey: ['rfq', rfqId] })
  }
  void qc.invalidateQueries({ queryKey: ['purchase-request'] })
}

export function useCreateRfqMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateRfqPayload) => createRfq(payload),
    onSuccess: () => invalidateRfq(qc),
  })
}

export function usePublishRfqMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => publishRfq(id),
    onSuccess: (rfq) => invalidateRfq(qc, rfq.id),
  })
}

export function useCancelRfqMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => cancelRfq(id, reason),
    onSuccess: (rfq) => invalidateRfq(qc, rfq.id),
  })
}

export function useInviteSupplierMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ rfqId, supplierId }: { rfqId: number; supplierId: number }) =>
      inviteSupplierToRfq(rfqId, supplierId),
    onSuccess: (_data, vars) => invalidateRfq(qc, vars.rfqId),
  })
}

export function useCreateOfferMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ rfqId, payload }: { rfqId: number; payload: CreateOfferPayload }) =>
      createRfqOffer(rfqId, payload),
    onSuccess: (_data, vars) => invalidateRfq(qc, vars.rfqId),
  })
}

export function useSubmitOfferMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { offerId: number; rfqId: number }) => submitOffer(vars.offerId),
    onSuccess: (_data, vars) => invalidateRfq(qc, vars.rfqId),
  })
}

export function useWithdrawOfferMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (vars: { offerId: number; rfqId: number }) => withdrawOffer(vars.offerId),
    onSuccess: (_data, vars) => invalidateRfq(qc, vars.rfqId),
  })
}

export function useSelectSupplierMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ rfqId, payload }: { rfqId: number; payload: SelectSupplierPayload }) =>
      selectRfqSupplier(rfqId, payload),
    onSuccess: (rfq) => invalidateRfq(qc, rfq.id),
  })
}
