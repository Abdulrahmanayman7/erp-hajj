import type { DocumentCategoryFormState } from '../types/categories'
import type {
  Document,
  DocumentLinkableType,
  DocumentListState,
  DocumentStatus,
  DocumentUploadFormState,
  LockedDocumentLink,
} from '../types/documents'

export const DOCUMENT_MAX_SIZE_BYTES = 20 * 1024 * 1024

export const DOCUMENT_ALLOWED_EXTENSIONS = [
  'pdf',
  'jpg',
  'jpeg',
  'png',
  'webp',
  'doc',
  'docx',
  'xls',
  'xlsx',
  'txt',
] as const

export type DocumentAllowedExtension = (typeof DOCUMENT_ALLOWED_EXTENSIONS)[number]

export const DOCUMENTS_NAV = {
  key: 'documents',
  labelKey: 'nav.documents',
  to: '/app/documents',
  permission: 'documents.view',
} as const

export interface DocumentUploadFieldErrors {
  file?: string
  title?: string
  linkable_id?: string
  uploaded_from?: string
  uploaded_to?: string
}

export interface DocumentCategoryFieldErrors {
  name?: string
}

export interface DocumentMetadataFieldErrors {
  title?: string
  linkable_id?: string
}

export function fileExtension(filename: string): string {
  const idx = filename.lastIndexOf('.')
  if (idx < 0 || idx === filename.length - 1) {
    return ''
  }
  return filename.slice(idx + 1).toLowerCase()
}

export function isAllowedDocumentExtension(extension: string): boolean {
  return (DOCUMENT_ALLOWED_EXTENSIONS as readonly string[]).includes(extension.toLowerCase())
}

export function validateDocumentFile(file: File | null): string | undefined {
  if (!file) {
    return 'fileRequired'
  }
  if (file.size > DOCUMENT_MAX_SIZE_BYTES) {
    return 'fileTooLarge'
  }
  const ext = fileExtension(file.name)
  if (!ext || !isAllowedDocumentExtension(ext)) {
    return 'fileType'
  }
  return undefined
}

export function validateDocumentUploadForm(
  form: DocumentUploadFormState,
  lockedLink?: LockedDocumentLink | null,
): DocumentUploadFieldErrors {
  const errors: DocumentUploadFieldErrors = {}
  const fileError = validateDocumentFile(form.file)
  if (fileError) {
    errors.file = fileError
  }

  const type = lockedLink?.type ?? form.linkable_type
  const id = lockedLink?.id ?? form.linkable_id
  if (type && (id === '' || id == null)) {
    errors.linkable_id = 'linkRequired'
  }
  if (!type && id !== '' && id != null) {
    errors.linkable_id = 'linkRequired'
  }

  return errors
}

export function validateDocumentMetadataForm(input: {
  title: string
  linkable_type: DocumentLinkableType | ''
  linkable_id: number | ''
}): DocumentMetadataFieldErrors {
  const errors: DocumentMetadataFieldErrors = {}
  if (!input.title.trim()) {
    errors.title = 'required'
  }
  if (input.linkable_type && (input.linkable_id === '' || input.linkable_id == null)) {
    errors.linkable_id = 'linkRequired'
  }
  if (!input.linkable_type && input.linkable_id !== '' && input.linkable_id != null) {
    errors.linkable_id = 'linkRequired'
  }
  return errors
}

export function validateDocumentCategoryForm(
  form: DocumentCategoryFormState,
): DocumentCategoryFieldErrors {
  const errors: DocumentCategoryFieldErrors = {}
  if (!form.name.trim()) {
    errors.name = 'required'
  }
  return errors
}

export function resolveDocumentsListState(input: {
  isLoading: boolean
  isError: boolean
  count: number
}): DocumentListState {
  if (input.isLoading) return 'loading'
  if (input.isError) return 'error'
  if (input.count === 0) return 'empty'
  return 'ready'
}

export function documentStatusBadgeClass(status: DocumentStatus): string {
  if (status === 'archived') {
    return 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200/80'
  }
  return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
}

export function formatDocumentSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function truncateChecksum(checksum: string, visible = 12): string {
  if (checksum.length <= visible) {
    return checksum
  }
  return `${checksum.slice(0, visible)}…`
}

export function hostRouteForLink(
  type: DocumentLinkableType,
  id: number,
): string | null {
  switch (type) {
    case 'contract':
      return `/app/contracts/${id}`
    case 'meeting':
      return `/app/meetings/${id}`
    case 'decision':
      return `/app/decisions/${id}`
    case 'task':
      return `/app/tasks/${id}`
    case 'employee':
      return `/app/employees`
    case 'organization_unit':
      return `/app/organization`
    case 'warehouse':
      return `/app/warehouses/${id}`
    case 'inventory_item':
      return `/app/inventory/items/${id}`
    default:
      return null
  }
}

export function canShowDocumentAction(
  action: 'view' | 'download' | 'update' | 'archive' | 'restore' | 'delete' | 'upload' | 'manage_categories',
  can: (permission: string) => boolean,
  document?: Pick<Document, 'status'> | null,
): boolean {
  switch (action) {
    case 'view':
      return can('documents.view')
    case 'download':
      return can('documents.download')
    case 'update':
      return can('documents.update')
    case 'upload':
      return can('documents.upload')
    case 'manage_categories':
      return can('documents.manage_categories')
    case 'archive':
      return can('documents.archive') && (!document || document.status === 'active')
    case 'restore':
      return can('documents.archive') && (!document || document.status === 'archived')
    case 'delete':
      return can('documents.delete')
    default:
      return false
  }
}

export function isDocumentsNavVisible(can: (permission: string) => boolean): boolean {
  return can(DOCUMENTS_NAV.permission)
}

export function mapDocumentErrorCode(code: string | undefined): string {
  switch (code) {
    case 'DOCUMENT_INVALID_FILE':
    case 'DOCUMENT_FILE_TOO_LARGE':
    case 'DOCUMENT_LINK_INVALID':
    case 'DOCUMENT_INVALID_STATUS_TRANSITION':
    case 'DOCUMENT_FILE_MISSING':
    case 'DOCUMENT_CATEGORY_IN_USE':
    case 'DOCUMENT_CATEGORY_INVALID':
    case 'DOCUMENT_IMMUTABLE':
    case 'DOCUMENT_NUMBER_TAKEN':
    case 'DOCUMENT_NOT_FOUND':
      return code
    default:
      return 'generic'
  }
}

/** Ensure Document resource mocks never expose storage internals. */
export function assertNoStorageLeak(document: Record<string, unknown>): boolean {
  const forbidden = ['storage_path', 'storage_disk', 'disk', 'path', 'absolute_path']
  return !forbidden.some((key) => key in document)
}
