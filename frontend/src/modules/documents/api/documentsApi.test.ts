import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as documentsApi from './documentsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
  apiPostFormData: vi.fn(),
  apiDownloadBlob: vi.fn(),
}))

import {
  apiDelete,
  apiDownloadBlob,
  apiGet,
  apiPatch,
  apiPost,
  apiPostFormData,
} from '@/shared/api/http'

const sampleDocument = {
  id: 1,
  document_number: 'DOC-000001',
  title: 'عقد توريد',
  description: null,
  status: 'active',
  original_filename: 'contract.pdf',
  mime_type: 'application/pdf',
  extension: 'pdf',
  size_bytes: 204800,
  checksum_sha256: 'abc123',
  category: { id: 2, name: 'عقود' },
  link: { type: 'contract', id: 15, label: 'CTR-000003 — …' },
  uploaded_by: { id: 3, name: 'أحمد' },
  archived_at: null,
  created_at: '2026-08-10T10:00:00+00:00',
  updated_at: '2026-08-10T10:00:00+00:00',
}

describe('documentsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
    vi.mocked(apiPostFormData).mockReset()
    vi.mocked(apiDownloadBlob).mockReset()
  })

  it('lists documents with filters as query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [sampleDocument],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await documentsApi.listDocuments({
      search: 'عقد',
      status: 'active',
      category_id: 2,
      linkable_type: 'contract',
      linkable_id: 15,
    })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.document_number).toBe('DOC-000001')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/documents?search=%D8%B9%D9%82%D8%AF&status=active&category_id=2&linkable_type=contract&linkable_id=15',
    )
  })

  it('uploads via multipart FormData', async () => {
    vi.mocked(apiPostFormData).mockResolvedValue({
      success: true,
      message: '',
      data: sampleDocument,
    })

    const file = new File(['pdf'], 'contract.pdf', { type: 'application/pdf' })
    await documentsApi.uploadDocument(file, {
      title: 'عقد',
      category_id: 2,
      linkable_type: 'contract',
      linkable_id: 15,
    })

    expect(apiPostFormData).toHaveBeenCalledTimes(1)
    const [path, formData] = vi.mocked(apiPostFormData).mock.calls[0]!
    expect(path).toBe('/api/v1/documents')
    expect(formData).toBeInstanceOf(FormData)
    expect((formData as FormData).get('title')).toBe('عقد')
    expect((formData as FormData).get('category_id')).toBe('2')
    expect((formData as FormData).get('linkable_type')).toBe('contract')
    expect((formData as FormData).get('linkable_id')).toBe('15')
  })

  it('downloads via authorized blob API (not a storage URL)', async () => {
    const blob = new Blob(['bytes'], { type: 'application/pdf' })
    vi.mocked(apiDownloadBlob).mockResolvedValue({ blob, filename: 'contract.pdf' })

    const result = await documentsApi.downloadDocument(1)

    expect(apiDownloadBlob).toHaveBeenCalledWith('/api/v1/documents/1/download')
    expect(result.filename).toBe('contract.pdf')
  })

  it('archives, restores, updates and deletes', async () => {
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: '', data: sampleDocument })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { ...sampleDocument, title: 'Updated' },
    })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })

    await documentsApi.archiveDocument(1, { comment: 'done' })
    await documentsApi.restoreDocument(1)
    await documentsApi.updateDocument(1, { title: 'Updated' })
    await documentsApi.deleteDocument(1)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/documents/1/archive', { comment: 'done' })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/documents/1/restore', {})
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/documents/1', { title: 'Updated' })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/documents/1')
  })
})
