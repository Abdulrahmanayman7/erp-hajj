import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as api from './bankAccountsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

const BASE = '/api/v1/suppliers/9/bank-accounts'

function ok<T>(data: T) {
  return { success: true as const, message: '', data }
}

describe('bankAccountsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists masked bank accounts', async () => {
    vi.mocked(apiGet).mockResolvedValue(ok([{ id: 1, iban_masked: '****7519' }]))
    const rows = await api.listSupplierBankAccounts(9)
    expect(apiGet).toHaveBeenCalledWith(BASE)
    expect(rows[0]?.iban_masked).toBe('****7519')
  })

  it('creates with allowlisted fields only and SAR', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 1, status: 'pending' }))
    await api.createSupplierBankAccount(9, {
      account_holder_name: ' شركة ',
      bank_name: ' بنك ',
      iban: ' SA0380000000608010167519 ',
      currency: 'sar',
      evidence_document_id: 12,
    })
    expect(apiPost).toHaveBeenCalledWith(BASE, {
      account_holder_name: 'شركة',
      bank_name: 'بنك',
      label: null,
      iban: 'SA0380000000608010167519',
      account_number: null,
      currency: 'SAR',
      evidence_document_id: 12,
    })
  })

  it('updates pending via PATCH', async () => {
    vi.mocked(apiPatch).mockResolvedValue(ok({ id: 2 }))
    await api.updatePendingSupplierBankAccount(9, 2, {
      account_holder_name: 'أ',
      bank_name: 'ب',
      account_number: '12345678',
    })
    expect(apiPatch).toHaveBeenCalledWith(`${BASE}/2`, expect.objectContaining({
      account_number: '12345678',
      currency: 'SAR',
    }))
  })

  it('verify/reject/revoke/supersede/primary/reveal hit dedicated endpoints', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 3 }))
    await api.verifySupplierBankAccount(9, 3)
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/3/verify`, {})

    await api.rejectSupplierBankAccount(9, 3, '  سبب رفض تشغيلي  ')
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/3/reject`, { reason: 'سبب رفض تشغيلي' })

    await api.revokeSupplierBankAccount(9, 3, 'سبب إبطال كافٍ')
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/3/revoke`, { reason: 'سبب إبطال كافٍ' })

    await api.supersedeSupplierBankAccount(9, 3, {
      account_holder_name: 'أ',
      bank_name: 'ب',
      iban: 'SA4420000001234567891234',
    })
    expect(apiPost).toHaveBeenLastCalledWith(
      `${BASE}/3/supersede`,
      expect.objectContaining({ iban: 'SA4420000001234567891234' }),
    )

    await api.setPrimarySupplierBankAccount(9, 3)
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/3/set-primary`, {})

    await api.clearPrimarySupplierBankAccount(9, 3)
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/3/clear-primary`, {})

    await api.revealSupplierBankAccount(9, 3, '  مراجعة تشغيلية مشروعة ')
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/3/reveal`, {
      reason: 'مراجعة تشغيلية مشروعة',
    })
  })
})
