import { computed, type Ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import { getGoodsReceipt, listGoodsReceipts } from '../api/goodsReceiptsApi'

export function useGoodsReceiptsQuery(filters: {
  status?: string
  q?: string
  page?: number
  purchase_order_id?: number
}) {
  return useQuery({
    queryKey: computed(() => ['goods-receipts', { ...filters }]),
    queryFn: () => listGoodsReceipts(filters),
  })
}

export function useGoodsReceiptQuery(id: Ref<number | null>) {
  return useQuery({
    queryKey: computed(() => ['goods-receipt', id.value]),
    queryFn: () => getGoodsReceipt(id.value as number),
    enabled: computed(() => id.value != null && id.value > 0),
  })
}
