import { apiGet } from '@/shared/api/http'

import type {
  InventoryBalance,
  InventoryBalancesListMeta,
  ListInventoryBalancesParams,
} from '../types/balances'

function toQuery(params: ListInventoryBalancesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listInventoryBalances(
  params: ListInventoryBalancesParams = {},
): Promise<{ data: InventoryBalance[]; meta: InventoryBalancesListMeta }> {
  const response = await apiGet<InventoryBalance[]>(`/api/v1/inventory/balances${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as InventoryBalancesListMeta,
  }
}
