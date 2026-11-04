import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as rfqApi from './rfqApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('rfqApi part2', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists rfqs with status and q filters', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, rfq_number: 'RFQ-000001', title: 'طلب', status: 'draft', purchase_request_id: 9 }],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await rfqApi.listRfqs({ status: 'draft', q: 'طلب', page: 1 })

    expect(result.data).toHaveLength(1)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/rfqs?status=draft&q=%D8%B7%D9%84%D8%A8&page=1')
  })

  it('invites supplier and lists offers', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 11, supplier_id: 5, status: 'invited' },
    })
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 21, offer_number: 'OFF-000001', status: 'submitted', supplier_id: 5, procurement_rfq_id: 3 }],
    })

    const invitation = await rfqApi.inviteSupplierToRfq(3, 5)
    const offers = await rfqApi.listRfqOffers(3)

    expect(invitation.supplier_id).toBe(5)
    expect(offers[0].offer_number).toBe('OFF-000001')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/rfqs/3/invitations', { supplier_id: 5 })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/rfqs/3/offers')
  })

  it('submits withdraws and selects supplier', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 3, status: 'awarded', selected_supplier_id: 5 },
    })

    await rfqApi.submitOffer(21)
    await rfqApi.withdrawOffer(21)
    await rfqApi.selectRfqSupplier(3, {
      supplier_id: 5,
      selection_rationale: 'أفضل عرض',
      offer_id: 21,
    })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/offers/21/submit')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/offers/21/withdraw')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/rfqs/3/select-supplier', {
      supplier_id: 5,
      selection_rationale: 'أفضل عرض',
      offer_id: 21,
    })
  })

  it('updates draft offer via patch', async () => {
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 21, status: 'draft', notes: 'محدث' },
    })

    await rfqApi.updateOffer(21, { notes: 'محدث' })

    expect(apiPatch).toHaveBeenCalledWith('/api/v1/offers/21', { notes: 'محدث' })
  })
})
