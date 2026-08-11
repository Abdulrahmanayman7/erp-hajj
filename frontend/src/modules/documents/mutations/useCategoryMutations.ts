import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  createDocumentCategory,
  deleteDocumentCategory,
  updateDocumentCategory,
} from '../api/categoriesApi'
import { documentCategoriesQueryKey } from '../queries/useCategoriesQuery'
import { documentsQueryKey } from '../queries/useDocumentsQuery'
import type {
  CreateDocumentCategoryPayload,
  UpdateDocumentCategoryPayload,
} from '../types/categories'

async function invalidateCategories(
  client: ReturnType<typeof useQueryClient>,
  touchDocuments = false,
): Promise<void> {
  await client.invalidateQueries({ queryKey: documentCategoriesQueryKey })
  if (touchDocuments) {
    await client.invalidateQueries({ queryKey: documentsQueryKey })
  }
}

export function useCreateDocumentCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateDocumentCategoryPayload) => createDocumentCategory(payload),
    onSuccess: async () => {
      await invalidateCategories(client)
    },
  })
}

export function useUpdateDocumentCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateDocumentCategoryPayload }) =>
      updateDocumentCategory(id, payload),
    onSuccess: async (_data, vars) => {
      await invalidateCategories(client, vars.payload.is_active !== undefined)
    },
  })
}

export function useDeleteDocumentCategoryMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteDocumentCategory(id),
    onSuccess: async () => {
      await invalidateCategories(client)
    },
  })
}
