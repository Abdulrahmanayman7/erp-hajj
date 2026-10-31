import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as suppliersApi from './suppliersApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('suppliersApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists suppliers with q and status query params', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, supplier_number: 'SUP-000001', display_name: 'مورد', status: 'draft' }],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await suppliersApi.listSuppliers({ q: 'مورد', status: 'draft' })

    expect(result.data).toHaveLength(1)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/suppliers?q=%D9%85%D9%88%D8%B1%D8%AF&status=draft')
  })

  it('creates supplier and returns warnings', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, display_name: 'مورد' },
      warnings: [{ code: 'SUPPLIER_SIMILAR_DISPLAY_NAME', message: 'تنبيه' }],
    })

    const result = await suppliersApi.createSupplier({
      display_name: 'مورد',
      supplier_type: 'company',
      country_code: 'SA',
    })

    expect(result.warnings).toHaveLength(1)
    expect(result.supplier.id).toBe(1)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/suppliers', {
      display_name: 'مورد',
      supplier_type: 'company',
      country_code: 'SA',
    })
  })

  it('transitions supplier status', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, status: 'under_review' },
    })

    await suppliersApi.transitionSupplier(1, { to: 'under_review' })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/suppliers/1/transition', {
      to: 'under_review',
    })
  })

  it('updates supplier via patch', async () => {
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, display_name: 'updated' },
      warnings: [],
    })

    await suppliersApi.updateSupplier(1, { display_name: 'updated' })

    expect(apiPatch).toHaveBeenCalledWith('/api/v1/suppliers/1', { display_name: 'updated' })
  })
})
