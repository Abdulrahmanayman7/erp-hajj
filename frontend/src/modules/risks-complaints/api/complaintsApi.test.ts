import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiGet = vi.fn()
const apiPost = vi.fn()

vi.mock('@/shared/api/http', () => ({
  apiGet: (...args: unknown[]) => apiGet(...args),
  apiPost: (...args: unknown[]) => apiPost(...args),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '99' }),
  }),
}))

import { createComplaint, getComplaintTimeline, listComplaints } from './complaintsApi'

describe('complaintsApi', () => {
  beforeEach(() => {
    apiGet.mockReset()
    apiPost.mockReset()
  })

  it('sends X-Season-Id on list', async () => {
    apiGet.mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 15, total: 0, last_page: 1 } })
    await listComplaints({ seasonId: 8, status: 'new' })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/complaints?status=new', {
      headers: { 'X-Season-Id': '8' },
    })
  })

  it('sends X-Season-Id on create', async () => {
    apiPost.mockResolvedValue({ data: { id: 2 } })
    await createComplaint(
      { title: 'شكوى', body: 'نص', category_code: 'service' },
      3,
    )
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/complaints',
      { title: 'شكوى', body: 'نص', category_code: 'service' },
      { headers: { 'X-Season-Id': '3' } },
    )
  })

  it('loads complaint timeline with season header', async () => {
    apiGet.mockResolvedValue({ data: [{ id: 5, event_type: 'COMPLAINT_CREATED' }] })
    const entries = await getComplaintTimeline(9, 4)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/complaints/9/timeline', {
      headers: { 'X-Season-Id': '4' },
    })
    expect(entries[0]?.event_type).toBe('COMPLAINT_CREATED')
  })
})
