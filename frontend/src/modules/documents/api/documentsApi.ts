import { apiDelete, apiDownloadBlob, apiGet, apiPatch, apiPost, apiPostFormData } from '@/shared/api/http'

import type {
  Document,
  DocumentTransitionPayload,
  DocumentsListMeta,
  ListDocumentsParams,
  UpdateDocumentPayload,
} from '../types/documents'

function toQuery(params: ListDocumentsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listDocuments(params: ListDocumentsParams = {}): Promise<{
  data: Document[]
  meta: DocumentsListMeta
}> {
  const response = await apiGet<Document[]>(`/api/v1/documents${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as DocumentsListMeta,
  }
}

export async function getDocument(id: number): Promise<Document> {
  const response = await apiGet<Document>(`/api/v1/documents/${id}`)
  return response.data
}

export interface UploadDocumentFields {
  title?: string
  description?: string | null
  category_id?: number | null
  linkable_type?: string | null
  linkable_id?: number | null
}

export async function uploadDocument(file: File, fields: UploadDocumentFields = {}): Promise<Document> {
  const formData = new FormData()
  formData.append('file', file)

  if (fields.title != null && fields.title !== '') {
    formData.append('title', fields.title)
  }
  if (fields.description != null && fields.description !== '') {
    formData.append('description', fields.description)
  }
  if (fields.category_id != null) {
    formData.append('category_id', String(fields.category_id))
  }
  if (fields.linkable_type) {
    formData.append('linkable_type', fields.linkable_type)
  }
  if (fields.linkable_id != null) {
    formData.append('linkable_id', String(fields.linkable_id))
  }

  const response = await apiPostFormData<Document>('/api/v1/documents', formData)
  return response.data
}

export async function updateDocument(
  id: number,
  payload: UpdateDocumentPayload,
): Promise<Document> {
  const response = await apiPatch<Document>(`/api/v1/documents/${id}`, payload)
  return response.data
}

export async function downloadDocument(id: number): Promise<{ blob: Blob; filename: string | null }> {
  return apiDownloadBlob(`/api/v1/documents/${id}/download`)
}

export async function archiveDocument(
  id: number,
  payload: DocumentTransitionPayload = {},
): Promise<Document> {
  const response = await apiPost<Document>(`/api/v1/documents/${id}/archive`, payload)
  return response.data
}

export async function restoreDocument(
  id: number,
  payload: DocumentTransitionPayload = {},
): Promise<Document> {
  const response = await apiPost<Document>(`/api/v1/documents/${id}/restore`, payload)
  return response.data
}

export async function deleteDocument(id: number): Promise<void> {
  await apiDelete(`/api/v1/documents/${id}`)
}

/** Trigger a browser file save from an authorized blob download. */
export function triggerBrowserDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.rel = 'noopener'
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}
