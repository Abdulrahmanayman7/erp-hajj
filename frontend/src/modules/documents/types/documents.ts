export type DocumentStatus = 'active' | 'archived'

export type DocumentLinkableType =
  | 'contract'
  | 'meeting'
  | 'decision'
  | 'task'
  | 'employee'
  | 'organization_unit'

export const DOCUMENT_LINKABLE_TYPES: DocumentLinkableType[] = [
  'contract',
  'meeting',
  'decision',
  'task',
  'employee',
  'organization_unit',
]

export interface DocumentCategorySummary {
  id: number
  name: string
  is_active?: boolean
}

export interface DocumentActorSummary {
  id: number
  name: string
}

export interface DocumentLink {
  type: DocumentLinkableType
  id: number
  label: string | null
}

export interface Document {
  id: number
  document_number: string
  title: string
  description: string | null
  status: DocumentStatus
  original_filename: string
  mime_type: string
  extension: string
  size_bytes: number
  checksum_sha256: string
  category: DocumentCategorySummary | null
  link: DocumentLink | null
  uploaded_by: DocumentActorSummary | null
  archived_at: string | null
  archived_by?: DocumentActorSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface DocumentsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListDocumentsParams {
  search?: string
  status?: DocumentStatus | 'all' | ''
  category_id?: number | ''
  uploaded_by?: number | ''
  linkable_type?: DocumentLinkableType | ''
  linkable_id?: number | ''
  uploaded_from?: string
  uploaded_to?: string
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface UpdateDocumentPayload {
  title?: string
  description?: string | null
  category_id?: number | null
  linkable_type?: DocumentLinkableType | null
  linkable_id?: number | null
}

export interface DocumentTransitionPayload {
  comment?: string | null
}

export interface DocumentUploadFormState {
  file: File | null
  title: string
  description: string
  category_id: number | ''
  linkable_type: DocumentLinkableType | ''
  linkable_id: number | ''
}

export interface LockedDocumentLink {
  type: DocumentLinkableType
  id: number
  label?: string
}

export type DocumentListState = 'loading' | 'error' | 'empty' | 'ready'
