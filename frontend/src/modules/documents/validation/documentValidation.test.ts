import { describe, expect, it } from 'vitest'

import { ar } from '@/locales/ar'

import type { Document } from '../types/documents'
import {
  DOCUMENTS_NAV,
  DOCUMENT_MAX_SIZE_BYTES,
  assertNoStorageLeak,
  canShowDocumentAction,
  documentStatusBadgeClass,
  fileExtension,
  formatDocumentSize,
  hostRouteForLink,
  isDocumentsNavVisible,
  resolveDocumentsListState,
  truncateChecksum,
  validateDocumentCategoryForm,
  validateDocumentFile,
  validateDocumentMetadataForm,
  validateDocumentUploadForm,
} from './documentValidation'

function makeDocument(overrides: Partial<Document> = {}): Document {
  return {
    id: 1,
    document_number: 'DOC-000001',
    title: 'مستند',
    description: null,
    status: 'active',
    original_filename: 'file.pdf',
    mime_type: 'application/pdf',
    extension: 'pdf',
    size_bytes: 1024,
    checksum_sha256: 'abcdef1234567890checksum',
    category: null,
    link: null,
    uploaded_by: null,
    archived_at: null,
    created_at: null,
    updated_at: null,
    ...overrides,
  }
}

describe('documents list states', () => {
  it('resolves loading / error / empty / ready', () => {
    expect(resolveDocumentsListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveDocumentsListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveDocumentsListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveDocumentsListState({ isLoading: false, isError: false, count: 2 })).toBe('ready')
  })
})

describe('upload validation', () => {
  it('requires a file and rejects oversized / forbidden types', () => {
    expect(validateDocumentFile(null)).toBe('fileRequired')

    const huge = new File([new Uint8Array(DOCUMENT_MAX_SIZE_BYTES + 1)], 'big.pdf', {
      type: 'application/pdf',
    })
    expect(validateDocumentFile(huge)).toBe('fileTooLarge')

    const bad = new File(['x'], 'malware.exe', { type: 'application/octet-stream' })
    expect(validateDocumentFile(bad)).toBe('fileType')

    const ok = new File(['x'], 'ok.pdf', { type: 'application/pdf' })
    expect(validateDocumentFile(ok)).toBeUndefined()
  })

  it('accepts allowed extensions', () => {
    for (const ext of ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt']) {
      expect(fileExtension(`a.${ext}`)).toBe(ext)
      expect(validateDocumentFile(new File(['x'], `a.${ext}`))).toBeUndefined()
    }
  })

  it('requires linkable_id when type is set (and vice versa)', () => {
    const file = new File(['x'], 'a.pdf')
    expect(
      validateDocumentUploadForm({
        file,
        title: '',
        description: '',
        category_id: '',
        linkable_type: 'contract',
        linkable_id: '',
      }),
    ).toMatchObject({ linkable_id: 'linkRequired' })

    expect(
      validateDocumentUploadForm({
        file,
        title: '',
        description: '',
        category_id: '',
        linkable_type: '',
        linkable_id: 5,
      }),
    ).toMatchObject({ linkable_id: 'linkRequired' })

    expect(
      validateDocumentUploadForm(
        {
          file,
          title: '',
          description: '',
          category_id: '',
          linkable_type: '',
          linkable_id: '',
        },
        { type: 'task', id: 9 },
      ),
    ).toEqual({})
  })
})

describe('permission gating', () => {
  it('gates download / archive / restore / delete / categories', () => {
    const can = (p: string) =>
      ['documents.view', 'documents.download', 'documents.archive'].includes(p)

    expect(canShowDocumentAction('download', can)).toBe(true)
    expect(canShowDocumentAction('delete', can)).toBe(false)
    expect(canShowDocumentAction('manage_categories', can)).toBe(false)
    expect(canShowDocumentAction('archive', can, makeDocument({ status: 'active' }))).toBe(true)
    expect(canShowDocumentAction('restore', can, makeDocument({ status: 'active' }))).toBe(false)
    expect(canShowDocumentAction('restore', can, makeDocument({ status: 'archived' }))).toBe(true)
    expect(canShowDocumentAction('archive', can, makeDocument({ status: 'archived' }))).toBe(false)
  })

  it('hides upload CTA without documents.upload', () => {
    expect(canShowDocumentAction('upload', () => false)).toBe(false)
    expect(canShowDocumentAction('upload', (p) => p === 'documents.upload')).toBe(true)
  })
})

describe('archive / restore / delete UX visibility', () => {
  it('maps status badge classes', () => {
    expect(documentStatusBadgeClass('active')).toContain('emerald')
    expect(documentStatusBadgeClass('archived')).toContain('neutral')
  })
})

describe('category manager', () => {
  it('requires category name', () => {
    expect(validateDocumentCategoryForm({ name: '', description: '' })).toMatchObject({
      name: 'required',
    })
    expect(validateDocumentCategoryForm({ name: 'عقود', description: '' })).toEqual({})
  })

  it('gates manage_categories permission', () => {
    expect(canShowDocumentAction('manage_categories', (p) => p === 'documents.manage_categories')).toBe(
      true,
    )
  })
})

describe('entity documents section helpers', () => {
  it('resolves host routes for linkable types', () => {
    expect(hostRouteForLink('contract', 3)).toBe('/app/contracts/3')
    expect(hostRouteForLink('meeting', 3)).toBe('/app/meetings/3')
    expect(hostRouteForLink('decision', 3)).toBe('/app/decisions/3')
    expect(hostRouteForLink('task', 3)).toBe('/app/tasks/3')
    expect(hostRouteForLink('employee', 3)).toBe('/app/employees')
    expect(hostRouteForLink('organization_unit', 3)).toBe('/app/organization')
    expect(hostRouteForLink('warehouse', 3)).toBe('/app/warehouses/3')
    expect(hostRouteForLink('inventory_item', 3)).toBe('/app/inventory/items/3')
  })

  it('uses المستندات section title in locale', () => {
    expect(ar.documents.entitySection.title).toBe('المستندات')
  })
})

describe('sidebar visibility', () => {
  it('shows documents nav only with documents.view', () => {
    expect(DOCUMENTS_NAV.to).toBe('/app/documents')
    expect(DOCUMENTS_NAV.permission).toBe('documents.view')
    expect(ar.nav.documents).toBe('الوثائق')
    expect(isDocumentsNavVisible((p) => p === 'documents.view')).toBe(true)
    expect(isDocumentsNavVisible(() => false)).toBe(false)
  })
})

describe('details metadata', () => {
  it('never includes storage path fields in resource shape helpers', () => {
    const resource = {
      id: 1,
      document_number: 'DOC-000001',
      title: 'x',
      checksum_sha256: 'abcdef1234567890',
    }
    expect(assertNoStorageLeak(resource)).toBe(true)
    expect(assertNoStorageLeak({ ...resource, storage_path: 'secret' })).toBe(false)
    expect(truncateChecksum('abcdef1234567890checksum')).toContain('…')
  })

  it('validates metadata edit form', () => {
    expect(
      validateDocumentMetadataForm({ title: '', linkable_type: '', linkable_id: '' }),
    ).toMatchObject({ title: 'required' })
  })

  it('formats sizes', () => {
    expect(formatDocumentSize(500)).toBe('500 B')
    expect(formatDocumentSize(2048)).toContain('KB')
    expect(formatDocumentSize(2 * 1024 * 1024)).toContain('MB')
  })
})

describe('filters defaults', () => {
  it('defaults list status filter to active in locale labels', () => {
    expect(ar.documents.status.active).toBe('نشط')
    expect(ar.documents.status.archived).toBe('مؤرشف')
    expect(ar.documents.filters.allStatuses).toBe('الكل')
  })
})
