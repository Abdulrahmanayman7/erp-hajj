import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateInventoryItem,
  createInventoryItem,
  deactivateInventoryItem,
  deleteInventoryItem,
  updateInventoryItem,
} from '../api/itemsApi'
import { inventoryBalancesQueryKey } from '../queries/useInventoryBalancesQuery'
import {
  inventoryItemBalancesQueryKey,
  inventoryItemDetailQueryKey,
  inventoryItemsQueryKey,
} from '../queries/useItemsQuery'
import type { InventoryItem, InventoryItemPayload } from '../types/items'

async function invalidateItems(
  client: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await client.invalidateQueries({ queryKey: inventoryItemsQueryKey })
  await client.invalidateQueries({ queryKey: inventoryBalancesQueryKey })
  if (id) {
    await client.invalidateQueries({ queryKey: inventoryItemDetailQueryKey(id) })
    await client.invalidateQueries({ queryKey: inventoryItemBalancesQueryKey(id) })
  }
}

export function useCreateInventoryItemMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createInventoryItem,
    onSuccess: (item: InventoryItem) => invalidateItems(client, item.id),
  })
}

export function useUpdateInventoryItemMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: InventoryItemPayload }) =>
      updateInventoryItem(id, payload),
    onSuccess: (item: InventoryItem) => invalidateItems(client, item.id),
  })
}

export function useActivateInventoryItemMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateInventoryItem(id),
    onSuccess: (item: InventoryItem) => invalidateItems(client, item.id),
  })
}

export function useDeactivateInventoryItemMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateInventoryItem(id),
    onSuccess: (item: InventoryItem) => invalidateItems(client, item.id),
  })
}

export function useDeleteInventoryItemMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteInventoryItem(id),
    onSuccess: (_, id) => invalidateItems(client, id),
  })
}
