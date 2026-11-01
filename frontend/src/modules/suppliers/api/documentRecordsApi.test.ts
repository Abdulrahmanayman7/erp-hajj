import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as api from './documentRecordsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}))

import { apiGet, apiPost } from '@/shared/api/http'

const BASE = '/api/v1/suppliers/9/document-records'

function ok<T>(data: T) {
  return { success: true as const, message: '', data }
}

describe('documentRecordsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
  })

  it('lists document records for a supplier', async () => {
    vi.mocked(apiGet).mockResolvedValue(ok([{ id: 1 }]))
    const records = await api.listSupplierDocumentRecords(9)
    expect(apiGet).toHaveBeenCalledWith(BASE)
    expect(records).toHaveLength(1)
  })

  it('creates a record with requirement_def_id, document_id and expires_at', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 1, verification_status: 'pending_verification' }))
    await api.createSupplierDocumentRecord(9, {
      requirement_def_id: 100,
      document_id: 555,
      expires_at: '2027-10-31',
    })
    expect(apiPost).toHaveBeenCalledWith(BASE, {
      requirement_def_id: 100,
      document_id: 555,
      expires_at: '2027-10-31',
    })
  })

  it('verifies with an empty body or a trimmed comment', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 1, verification_status: 'valid' }))
    await api.verifySupplierDocumentRecord(9, 1)
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/1/verify`, {})
    await api.verifySupplierDocumentRecord(9, 1, ' تم ')
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/1/verify`, { comment: 'تم' })
  })

  it('rejects with a rejection_reason', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 1, verification_status: 'rejected' }))
    await api.rejectSupplierDocumentRecord(9, 1, 'غير واضحة')
    expect(apiPost).toHaveBeenCalledWith(`${BASE}/1/reject`, { rejection_reason: 'غير واضحة' })
  })

  it('supersedes with a new document', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 2, supersedes_id: 1 }))
    await api.supersedeSupplierDocumentRecord(9, 1, { document_id: 556, expires_at: '2028-01-15' })
    expect(apiPost).toHaveBeenCalledWith(`${BASE}/1/supersede`, {
      document_id: 556,
      expires_at: '2028-01-15',
    })
  })
})
