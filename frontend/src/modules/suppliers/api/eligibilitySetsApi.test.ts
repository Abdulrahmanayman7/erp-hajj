import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as api from './eligibilitySetsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

const BASE = '/api/v1/supplier-eligibility-requirement-sets'

function ok<T>(data: T, meta?: Record<string, unknown>) {
  return { success: true as const, message: '', data, meta }
}

describe('eligibilitySetsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPatch).mockReset()
  })

  it('lists sets with config_key/status filters and drops empty params', async () => {
    vi.mocked(apiGet).mockResolvedValue(
      ok([{ id: 1 }], { current_page: 1, per_page: 15, total: 1, last_page: 1 }),
    )

    const result = await api.listEligibilitySets({ config_key: 'default', status: 'draft', page: 2 })

    expect(apiGet).toHaveBeenCalledWith(`${BASE}?config_key=default&status=draft&page=2`)
    expect(result.data).toHaveLength(1)
    expect(result.meta.last_page).toBe(1)

    vi.mocked(apiGet).mockResolvedValue(ok([], {}))
    await api.listEligibilitySets({ status: '' })
    expect(apiGet).toHaveBeenLastCalledWith(BASE)
  })

  it('shows a set', async () => {
    vi.mocked(apiGet).mockResolvedValue(ok({ id: 10, requirement_defs: [] }))
    const set = await api.getEligibilitySet(10)
    expect(apiGet).toHaveBeenCalledWith(`${BASE}/10`)
    expect(set.id).toBe(10)
  })

  it('creates a draft set', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 11, status: 'draft' }))
    const payload = {
      config_key: 'default',
      name: 'أساسي',
      requirement_defs: [
        {
          requirement_key: 'commercial_registration',
          label_ar: 'السجل التجاري',
          supplier_type: 'company' as const,
          is_mandatory: true,
        },
      ],
    }
    await api.createEligibilitySet(payload)
    expect(apiPost).toHaveBeenCalledWith(BASE, payload)
  })

  it('patches a draft set', async () => {
    vi.mocked(apiPatch).mockResolvedValue(ok({ id: 11 }))
    await api.updateEligibilitySet(11, { name: 'جديد' })
    expect(apiPatch).toHaveBeenCalledWith(`${BASE}/11`, { name: 'جديد' })
  })

  it('approves with an empty body or a trimmed comment', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 11, status: 'approved' }))
    await api.approveEligibilitySet(11)
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/11/approve`, {})
    await api.approveEligibilitySet(11, '  موافق  ')
    expect(apiPost).toHaveBeenLastCalledWith(`${BASE}/11/approve`, { comment: 'موافق' })
  })

  it('creates a new draft from an approved version', async () => {
    vi.mocked(apiPost).mockResolvedValue(ok({ id: 12, version_no: 2, status: 'draft' }))
    const draft = await api.newDraftFromEligibilitySet(10)
    expect(apiPost).toHaveBeenCalledWith(`${BASE}/10/new-draft`, {})
    expect(draft.version_no).toBe(2)
  })
})
