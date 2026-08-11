import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  adjustStock,
  issueStock,
  receiveStock,
  returnStock,
  transferStock,
} from '../api/stockApi'
import { inventoryBalancesQueryKey } from '../queries/useInventoryBalancesQuery'
import { inventoryItemsQueryKey } from '../queries/useItemsQuery'
import { inventoryMovementsQueryKey } from '../queries/useMovementsQuery'
import { warehousesQueryKey } from '../queries/useWarehousesQuery'
import type {
  AdjustStockPayload,
  IssueStockPayload,
  ReceiveStockPayload,
  ReturnStockPayload,
  TransferStockPayload,
} from '../types/stock'

async function invalidateStockQueries(client: ReturnType<typeof useQueryClient>): Promise<void> {
  await Promise.all([
    client.invalidateQueries({ queryKey: inventoryBalancesQueryKey }),
    client.invalidateQueries({ queryKey: inventoryMovementsQueryKey }),
    client.invalidateQueries({ queryKey: inventoryItemsQueryKey }),
    client.invalidateQueries({ queryKey: warehousesQueryKey }),
  ])
}

export function useReceiveStockMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: ReceiveStockPayload) => receiveStock(payload),
    onSuccess: () => invalidateStockQueries(client),
  })
}

export function useIssueStockMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: IssueStockPayload) => issueStock(payload),
    onSuccess: () => invalidateStockQueries(client),
  })
}

export function useReturnStockMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: ReturnStockPayload) => returnStock(payload),
    onSuccess: () => invalidateStockQueries(client),
  })
}

export function useTransferStockMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: TransferStockPayload) => transferStock(payload),
    onSuccess: () => invalidateStockQueries(client),
  })
}

export function useAdjustStockMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: AdjustStockPayload) => adjustStock(payload),
    onSuccess: () => invalidateStockQueries(client),
  })
}
