import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  createSupplierCategory,
  deactivateSupplierCategory,
  deleteSupplierCategory,
  updateSupplierCategory,
} from '../api/categoriesApi'
import { supplierCategoriesQueryKey } from '../queries/useCategoriesQuery'
import type { CreateCategoryPayload, UpdateCategoryPayload } from '../types/categories'

export function useCreateSupplierCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateCategoryPayload) => createSupplierCategory(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: supplierCategoriesQueryKey })
    },
  })
}

export function useUpdateSupplierCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateCategoryPayload }) =>
      updateSupplierCategory(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: supplierCategoriesQueryKey })
    },
  })
}

export function useDeactivateSupplierCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateSupplierCategory(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: supplierCategoriesQueryKey })
    },
  })
}

export function useDeleteSupplierCategoryMutation() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteSupplierCategory(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: supplierCategoriesQueryKey })
    },
  })
}
