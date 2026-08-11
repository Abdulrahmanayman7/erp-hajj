import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as contractsApi from './contractsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

describe('contractsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiDelete).mockReset()
  })

  it('lists contracts with filters as query string', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [
        {
          id: 1,
          contract_number: 'CTR-000001',
          title: 'عقد توريد',
          status: 'draft',
          counterparty_name: 'مورد',
          counterparty_kind: 'organization',
          start_date: '2026-01-01',
          end_date: '2026-12-31',
          value: '1000.00',
          currency: 'SAR',
          notes: null,
          is_expiring_soon: false,
          category: { id: 1, name: 'توريد', code: 'sup' },
          employee: null,
          organization_unit: null,
          created_by: null,
          renewed_from_contract_id: null,
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 },
    })

    const result = await contractsApi.listContracts({
      search: 'توريد',
      status: 'draft',
      category_id: 1,
      expiring_soon: true,
    })

    expect(result.data).toHaveLength(1)
    expect(result.data[0]?.contract_number).toBe('CTR-000001')
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/contracts?search=%D8%AA%D9%88%D8%B1%D9%8A%D8%AF&status=draft&category_id=1&expiring_soon=1',
    )
  })

  it('creates, updates and deletes contracts', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, title: 'New', contract_number: 'CTR-000002' },
    })
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, title: 'Updated' },
    })
    vi.mocked(apiDelete).mockResolvedValue({
      success: true,
      message: '',
      data: null,
    })

    await contractsApi.createContract({
      title: 'New',
      contract_category_id: 1,
      counterparty_name: 'طرف',
      start_date: '2026-01-01',
    })
    await contractsApi.updateContract(1, { title: 'Updated' })
    await contractsApi.deleteContract(1)

    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts', {
      title: 'New',
      contract_category_id: 1,
      counterparty_name: 'طرف',
      start_date: '2026-01-01',
    })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/contracts/1', { title: 'Updated' })
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/contracts/1')
  })

  it('calls lifecycle transition endpoints', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 1, status: 'in_review' },
    })

    await contractsApi.submitContractForReview(1)
    await contractsApi.returnContractToDraft(1, { comment: 'incomplete' })
    await contractsApi.approveContract(1)
    await contractsApi.signContract(1, { comment: 'signed offline' })
    await contractsApi.executeContract(1)
    await contractsApi.closeContract(1)
    await contractsApi.cancelContract(1, { comment: 'cancelled' })

    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/submit-review', {})
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/return-draft', {
      comment: 'incomplete',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/approve', {})
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/sign', {
      comment: 'signed offline',
    })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/execute', {})
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/close', {})
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/cancel', {
      comment: 'cancelled',
    })
  })

  it('renews contract and returns source + successor', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: {
        source: { id: 1, status: 'renewed' },
        successor: { id: 2, status: 'draft', contract_number: 'CTR-000099' },
      },
    })

    const result = await contractsApi.renewContract(1)

    expect(result.successor.id).toBe(2)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/contracts/1/renew', {})
  })
})
