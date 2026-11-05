import { computed, type MaybeRefOrGetter, type Ref, toValue } from 'vue'
import { useQuery } from '@tanstack/vue-query'

import { getPurchaseOrder, listPurchaseOrders } from '../api/purchaseOrdersApi'

export function usePurchaseOrdersQuery(
  filters: MaybeRefOrGetter<{
    status?: string
    q?: string
    page?: number
    rfq_id?: number
  }>,
) {
  return useQuery({
    queryKey: computed(() => ['purchase-orders', toValue(filters)]),
    queryFn: () => listPurchaseOrders(toValue(filters)),
  })
}

export function usePurchaseOrderQuery(id: Ref<number | null>) {
  return useQuery({
    queryKey: computed(() => ['purchase-order', id.value]),
    queryFn: () => getPurchaseOrder(id.value as number),
    enabled: computed(() => id.value != null && id.value > 0),
  })
}
