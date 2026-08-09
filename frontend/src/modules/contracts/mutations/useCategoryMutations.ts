import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateCategory,
  createCategory,
  deactivateCategory,
  deleteCategory,
  updateCategory,
} from '../api/categoriesApi'
import { categoriesQueryKey } from '../queries/useCategoriesQuery'
import { contractsQueryKey } from '../queries/useContractsQuery'
import type { CreateCategoryPayload, UpdateCategoryPayload } from '../types/categories'

async function invalidateCategoryQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  touchContracts = false,
): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: categoriesQueryKey })
  if (touchContracts) {
    await queryClient.invalidateQueries({ queryKey: contractsQueryKey })
  }
}

export function useCreateCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateCategoryPayload) => createCategory(payload),
    onSuccess: async () => {
      await invalidateCategoryQueries(queryClient)
    },
  })
}

export function useUpdateCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateCategoryPayload }) =>
      updateCategory(id, payload),
    onSuccess: async () => {
      await invalidateCategoryQueries(queryClient, true)
    },
  })
}

export function useActivateCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateCategory(id),
    onSuccess: async () => {
      await invalidateCategoryQueries(queryClient)
    },
  })
}

export function useDeactivateCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateCategory(id),
    onSuccess: async () => {
      await invalidateCategoryQueries(queryClient, true)
    },
  })
}

export function useDeleteCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteCategory(id),
    onSuccess: async () => {
      await invalidateCategoryQueries(queryClient)
    },
  })
}
