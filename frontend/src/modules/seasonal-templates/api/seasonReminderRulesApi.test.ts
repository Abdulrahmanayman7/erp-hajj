import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiGet = vi.fn()
const apiPost = vi.fn()
const apiPatch = vi.fn()

vi.mock('@/shared/api/http', () => ({
  apiGet: (...args: unknown[]) => apiGet(...args),
  apiPost: (...args: unknown[]) => apiPost(...args),
  apiPatch: (...args: unknown[]) => apiPatch(...args),
}))

import {
  activateSeasonReminderRule,
  archiveSeasonReminderRule,
  createSeasonReminderRule,
  getSeasonReminderRule,
  listSeasonReminderRules,
  pauseSeasonReminderRule,
  updateSeasonReminderRule,
} from './seasonReminderRulesApi'

const meta = { current_page: 1, per_page: 15, total: 0, last_page: 1 }

describe('seasonReminderRulesApi', () => {
  beforeEach(() => {
    apiGet.mockReset()
    apiPost.mockReset()
    apiPatch.mockReset()
  })

  it('lists rules under the season, dropping empty filters', async () => {
    apiGet.mockResolvedValue({ data: [], meta })
    await listSeasonReminderRules(4, { status: '', page: 2 })
    expect(apiGet.mock.calls[0]![0]).toBe('/api/v1/seasons/4/reminder-rules?page=2')
  })

  it('passes the status filter', async () => {
    apiGet.mockResolvedValue({ data: [], meta })
    await listSeasonReminderRules(4, { status: 'paused' })
    expect(apiGet.mock.calls[0]![0]).toBe('/api/v1/seasons/4/reminder-rules?status=paused')
  })

  it('uses the documented endpoints for CRUD and lifecycle', async () => {
    apiGet.mockResolvedValue({ data: { id: 9 } })
    apiPost.mockResolvedValue({ data: { id: 9 } })
    apiPatch.mockResolvedValue({ data: { id: 9 } })

    await getSeasonReminderRule(9)
    await createSeasonReminderRule(4, {
      code: 'A',
      title: 'T',
      severity: 'info',
      trigger_mode: 'absolute_date',
      trigger_on: '2027-05-20',
      recipient_permission: 'tasks.view',
    })
    await updateSeasonReminderRule(9, { title: 'N' })
    await activateSeasonReminderRule(9)
    await pauseSeasonReminderRule(9)
    await archiveSeasonReminderRule(9)

    expect(apiGet.mock.calls[0]![0]).toBe('/api/v1/season-reminder-rules/9')
    expect(apiPost.mock.calls.map((c) => c[0])).toEqual([
      '/api/v1/seasons/4/reminder-rules',
      '/api/v1/season-reminder-rules/9/activate',
      '/api/v1/season-reminder-rules/9/pause',
      '/api/v1/season-reminder-rules/9/archive',
    ])
    expect(apiPatch.mock.calls[0]![0]).toBe('/api/v1/season-reminder-rules/9')
    expect(apiPost.mock.calls[0]![1]).not.toHaveProperty('status')
  })
})
