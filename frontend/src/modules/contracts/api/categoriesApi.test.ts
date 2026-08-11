import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as categoriesApi from './categoriesApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('categoriesApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists categories with is_active filter', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, name: 'توريد', code: 'sup', is_active: true, created_at: null, updated_at: null }],
      meta: { current_page: 1, per_page: 50, total: 1, last_page: 1 },
    })

    const result = await categoriesApi.listCategories({ is_active: true, search: 'توريد' })

    expect(result.data[0]?.name).toBe('توريد')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/contract-categories?is_active=true&search=%D8%AA%D9%88%D8%B1%D9%8A%D8%AF',
    )
  })

  it('creates, updates, activates, deactivates and deletes categories', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 2, name: 'خدمات', code: null, is_active: true },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 2, name: 'خدمات ميدانية' },
    })
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: '',
      data: null,
    })

    await categoriesApi.createCategory({ name: 'خدمات' })
    await categoriesApi.updateCategory(2, { name: 'خدمات ميدانية' })
    await categoriesApi.activateCategory(2)
    await categoriesApi.deactivateCategory(2)
    await categoriesApi.deleteCategory(2)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/contract-categories', { name: 'خدمات' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/contract-categories/2', {
      name: 'خدمات ميدانية',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contract-categories/2/activate')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contract-categories/2/deactivate')
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/contract-categories/2')
  })
})
