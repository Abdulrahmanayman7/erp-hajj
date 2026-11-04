import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  cancelPurchaseOrder,
  closePurchaseOrder,
  createPurchaseOrder,
  issuePurchaseOrder,
  updatePurchaseOrder,
} from '../api/purchaseOrdersApi'
import type { CreatePurchaseOrderPayload, UpdatePurchaseOrderPayload } from '../types/purchaseOrder'

export function useCreatePurchaseOrderMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreatePurchaseOrderPayload) => createPurchaseOrder(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['rfq'] })
    },
  })
}

export function useUpdatePurchaseOrderMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdatePurchaseOrderPayload }) =>
      updatePurchaseOrder(id, payload),
    onSuccess: (po) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-order', po.id] })
    },
  })
}

export function useIssuePurchaseOrderMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => issuePurchaseOrder(id),
    onSuccess: (po) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-order', po.id] })
    },
  })
}

export function useCancelPurchaseOrderMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => cancelPurchaseOrder(id, reason),
    onSuccess: (po) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-order', po.id] })
    },
  })
}

export function useClosePurchaseOrderMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, closeReason }: { id: number; closeReason?: string | null }) =>
      closePurchaseOrder(id, closeReason),
    onSuccess: (po) => {
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
      void qc.invalidateQueries({ queryKey: ['purchase-order', po.id] })
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
    },
  })
}
