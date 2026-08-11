import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  createInventoryCategory,
  deleteInventoryCategory,
  updateInventoryCategory,
} from '../api/categoriesApi'
import { inventoryCategoriesQueryKey } from '../queries/useCategoriesQuery'
import type { UpdateInventoryCategoryPayload } from '../types/categories'
import { inventoryItemsQueryKey } from '../queries/useItemsQuery'

async function invalidateCategories(client: ReturnType<typeof useQueryClient>): Promise<void> {
  await client.invalidateQueries({ queryKey: inventoryCategoriesQueryKey })
  await client.invalidateQueries({ queryKey: inventoryItemsQueryKey })
}

export function useCreateInventoryCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createInventoryCategory,
    onSuccess: () => invalidateCategories(client),
  })
}

export function useUpdateInventoryCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateInventoryCategoryPayload }) =>
      updateInventoryCategory(id, payload),
    onSuccess: () => invalidateCategories(client),
  })
}

export function useDeleteInventoryCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteInventoryCategory(id),
    onSuccess: () => invalidateCategories(client),
  })
}
