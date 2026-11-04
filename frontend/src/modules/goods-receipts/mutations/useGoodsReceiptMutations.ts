import { useMutation, useQueryClient } from '@tanstack/vue-query'

import { createGoodsReceipt, postGoodsReceipt } from '../api/goodsReceiptsApi'
import type { CreateGoodsReceiptPayload } from '../types/goodsReceipt'

export function useCreateGoodsReceiptMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateGoodsReceiptPayload) => createGoodsReceipt(payload),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['purchase-order'] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })
}

export function usePostGoodsReceiptMutation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => postGoodsReceipt(id),
    onSuccess: (grn) => {
      void qc.invalidateQueries({ queryKey: ['goods-receipts'] })
      void qc.invalidateQueries({ queryKey: ['goods-receipt', grn.id] })
      void qc.invalidateQueries({ queryKey: ['purchase-order', grn.purchase_order_id] })
      void qc.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })
}
