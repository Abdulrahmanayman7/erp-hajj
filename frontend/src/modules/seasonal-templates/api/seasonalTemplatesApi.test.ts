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
  createSeasonCopyPreview,
  executeSeasonCopy,
  getSeasonCopyPreview,
  getSeasonCopyRun,
  listSeasonCopyRuns,
} from './seasonCopyApi'
import {
  approveSeasonTemplate,
  archiveSeasonTemplate,
  createSeasonTemplate,
  getSeasonTemplate,
  listSeasonTemplates,
  updateSeasonTemplate,
} from './seasonTemplatesApi'

const meta = { current_page: 1, per_page: 15, total: 0, last_page: 1 }

describe('seasonTemplatesApi', () => {
  beforeEach(() => {
    apiGet.mockReset()
    apiPost.mockReset()
    apiPatch.mockReset()
  })

  it('lists templates, dropping empty and "all" filters, with no season header', async () => {
    apiGet.mockResolvedValue({ data: [], meta })
    await listSeasonTemplates({ q: 'حج', status: 'all', page: 2 })
    expect(apiGet).toHaveBeenCalledTimes(1)
    expect(apiGet.mock.calls[0]).toHaveLength(1)
    expect(apiGet.mock.calls[0]![0]).toBe(`/api/v1/season-templates?q=${encodeURIComponent('حج')}&page=2`)
  })

  it('uses documented endpoints for CRUD and lifecycle', async () => {
    apiGet.mockResolvedValue({ data: { id: 3 } })
    apiPost.mockResolvedValue({ data: { id: 3 } })
    apiPatch.mockResolvedValue({ data: { id: 3 } })

    await getSeasonTemplate(3)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-templates/3')

    await createSeasonTemplate({ name: 'x', items: [{ entity_kind: 'season_programs' }] })
    expect(apiPost).toHaveBeenCalledWith('/api/v1/season-templates', {
      name: 'x',
      items: [{ entity_kind: 'season_programs' }],
    })

    await updateSeasonTemplate(3, { name: 'y' })
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/season-templates/3', { name: 'y' })

    await approveSeasonTemplate(3)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/season-templates/3/approve', {})

    await archiveSeasonTemplate(3)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/season-templates/3/archive', {})
  })
})

describe('seasonCopyApi', () => {
  beforeEach(() => {
    apiGet.mockReset()
    apiPost.mockReset()
  })

  it('posts previews with the backend body shape', async () => {
    apiPost.mockResolvedValue({ data: { id: 5 } })
    const payload = {
      mode: 'from_season' as const,
      source_season_id: 1,
      target_season_id: 2,
      selection: { entity_kinds: ['season_programs'] },
      date_mapping: { strategy: 'season_start_delta' as const },
    }
    await createSeasonCopyPreview(payload)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/season-copy/previews', payload)
  })

  it('fetches a preview by id', async () => {
    apiGet.mockResolvedValue({ data: { id: 5 } })
    await getSeasonCopyPreview(5)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-copy/previews/5')
  })

  it('executes with preview token, hash and resolutions', async () => {
    apiPost.mockResolvedValue({ data: { id: 8 } })
    const payload = {
      preview_token: '00000000-0000-4000-8000-000000000000',
      content_hash: 'a'.repeat(64),
      resolutions: [{ entity_kind: 'season_programs', natural_key: 'P1', decision: 'skip' as const }],
    }
    await executeSeasonCopy(payload)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/season-copy/executions', payload)
  })

  it('lists runs with filters and fetches a run', async () => {
    apiGet.mockResolvedValue({ data: [], meta })
    await listSeasonCopyRuns({ status: 'failed', target_season_id: 4, page: 1 })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-copy/runs?status=failed&target_season_id=4&page=1')

    apiGet.mockResolvedValue({ data: { id: 2 } })
    await getSeasonCopyRun(2)
    expect(apiGet).toHaveBeenLastCalledWith('/api/v1/season-copy/runs/2')
  })
})
