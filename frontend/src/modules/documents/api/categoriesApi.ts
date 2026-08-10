import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateDocumentCategoryPayload,
  DocumentCategory,
  ListDocumentCategoriesParams,
  UpdateDocumentCategoryPayload,
} from '../types/categories'

function toQuery(params: ListDocumentCategoriesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    if (typeof value === 'boolean') {
      query.set(key, value ? '1' : '0')
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listDocumentCategories(
  params: ListDocumentCategoriesParams = {},
): Promise<DocumentCategory[]> {
  const response = await apiGet<DocumentCategory[]>(
    `/api/v1/document-categories${toQuery(params)}`,
  )
  return response.data
}

export async function createDocumentCategory(
  payload: CreateDocumentCategoryPayload,
): Promise<DocumentCategory> {
  const response = await apiPost<DocumentCategory>('/api/v1/document-categories', payload)
  return response.data
}

export async function updateDocumentCategory(
  id: number,
  payload: UpdateDocumentCategoryPayload,
): Promise<DocumentCategory> {
  const response = await apiPatch<DocumentCategory>(`/api/v1/document-categories/${id}`, payload)
  return response.data
}

export async function deleteDocumentCategory(id: number): Promise<void> {
  await apiDelete(`/api/v1/document-categories/${id}`)
}
