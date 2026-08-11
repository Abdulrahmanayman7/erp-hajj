import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  createAssetCategory,
  deleteAssetCategory,
  updateAssetCategory,
} from '../api/categoriesApi'
import { assetCategoriesQueryKey } from '../queries/useCategoriesQuery'
import { assetsQueryKey } from '../queries/useAssetsQuery'
import type { UpdateAssetCategoryPayload } from '../types/categories'

async function invalidateCategories(client: ReturnType<typeof useQueryClient>): Promise<void> {
  await client.invalidateQueries({ queryKey: assetCategoriesQueryKey })
  await client.invalidateQueries({ queryKey: assetsQueryKey })
}

export function useCreateAssetCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createAssetCategory,
    onSuccess: () => invalidateCategories(client),
  })
}

export function useUpdateAssetCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateAssetCategoryPayload }) =>
      updateAssetCategory(id, payload),
    onSuccess: () => invalidateCategories(client),
  })
}

export function useDeleteAssetCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteAssetCategory(id),
    onSuccess: () => invalidateCategories(client),
  })
}
