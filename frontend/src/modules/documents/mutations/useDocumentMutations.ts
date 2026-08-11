import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  archiveDocument,
  deleteDocument,
  restoreDocument,
  updateDocument,
  uploadDocument,
  type UploadDocumentFields,
} from '../api/documentsApi'
import { documentDetailQueryKey, documentsQueryKey } from '../queries/useDocumentsQuery'
import type { Document, DocumentTransitionPayload, UpdateDocumentPayload } from '../types/documents'

async function invalidateDocuments(
  client: ReturnType<typeof useQueryClient>,
  id?: number,
): Promise<void> {
  await client.invalidateQueries({ queryKey: documentsQueryKey })
  if (id) {
    await client.invalidateQueries({ queryKey: documentDetailQueryKey(id) })
  }
}

export function useUploadDocumentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ file, fields }: { file: File; fields?: UploadDocumentFields }) =>
      uploadDocument(file, fields ?? {}),
    onSuccess: (document: Document) => invalidateDocuments(client, document.id),
  })
}

export function useUpdateDocumentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateDocumentPayload }) =>
      updateDocument(id, payload),
    onSuccess: (document: Document) => invalidateDocuments(client, document.id),
  })
}

export function useArchiveDocumentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: DocumentTransitionPayload }) =>
      archiveDocument(id, payload),
    onSuccess: (document: Document) => invalidateDocuments(client, document.id),
  })
}

export function useRestoreDocumentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: DocumentTransitionPayload }) =>
      restoreDocument(id, payload),
    onSuccess: (document: Document) => invalidateDocuments(client, document.id),
  })
}

export function useDeleteDocumentMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id }: { id: number }) => deleteDocument(id),
    onSuccess: (_data, vars) => invalidateDocuments(client, vars.id),
  })
}
