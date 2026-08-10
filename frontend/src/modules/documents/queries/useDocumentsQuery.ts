import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { getDocument, listDocuments } from '../api/documentsApi'
import type { ListDocumentsParams } from '../types/documents'

export const documentsQueryKey = ['documents'] as const
export const documentDetailQueryKey = (id: number) => [...documentsQueryKey, 'detail', id] as const

export function useDocumentsQuery(params: MaybeRefOrGetter<ListDocumentsParams>) {
  return useQuery({
    queryKey: computed(() => [...documentsQueryKey, 'list', toValue(params)]),
    queryFn: () => listDocuments(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useDocumentQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() =>
      toValue(id) == null
        ? [...documentsQueryKey, 'detail', 'unknown']
        : documentDetailQueryKey(toValue(id)!),
    ),
    queryFn: () => getDocument(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
