import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as categoriesApi from './categoriesApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('document categoriesApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists categories with active filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, name: 'عقود', description: null, is_active: true, created_at: null, updated_at: null }],
    })

    const result = await categoriesApi.listDocumentCategories({ active: true })

    expect(result).toHaveLength(1)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/document-categories?active=1')
  })

  it('creates, updates (including deactivate) and deletes categories', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, name: 'جديد', description: null, is_active: true },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, name: 'جديد', is_active: false },
    })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })

    await categoriesApi.createDocumentCategory({ name: 'جديد' })
    await categoriesApi.updateDocumentCategory(1, { is_active: false })
    await categoriesApi.deleteDocumentCategory(1)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/document-categories', { name: 'جديد' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/document-categories/1', { is_active: false })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/document-categories/1')
  })
})
