import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPut: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

// lifecycle helpers imported after mocks below

vi.mock('@/modules/accommodation/stores/seasonContextStore', () => ({
  useAccommodationSeasonStore: () => ({
    seasonHeaders: () => ({ 'X-Season-Id': '9' }),
    selectedSeasonId: 9,
  }),
}))

import { apiDelete, apiGet, apiPost, apiPut } from '@/shared/api/http'
import { createPilgrimGroup, listPilgrimGroups } from './pilgrimGroupsApi'
import { createSeasonProgram, listSeasonPrograms } from './programsApi'
import { createFamilyGroup, listFamilyGroups } from './familyGroupsApi'
import {
  activateSeasonPilgrim,
  assignSeasonPilgrimGroup,
  cancelSeasonPilgrim,
  changeSeasonPilgrimProgram,
  completeSeasonPilgrim,
  deleteSeasonPilgrim,
  registerSeasonPilgrim,
  setSeasonPilgrimFamilyGroup,
} from './seasonPilgrimsApi'

describe('season enrollment and ops APIs', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPost).mockReset()
    vi.mocked(apiPut).mockReset()
  })

  it('registers a season pilgrim with season header', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: { id: 10, reference_number: 'SP-1' },
    })

    await registerSeasonPilgrim(
      {
        pilgrim_id: 3,
        season_program_id: 2,
        season_pilgrim_group_id: 4,
      },
      7,
    )

    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims',
      {
        pilgrim_id: 3,
        season_program_id: 2,
        season_pilgrim_group_id: 4,
      },
      { headers: { 'X-Season-Id': '7' } },
    )
  })

  it('updates program, group and family membership', async () => {
    vi.mocked(apiPut).mockResolvedValue({ success: true, message: '', data: { id: 10 } })

    await changeSeasonPilgrimProgram(10, 2, 'تعديل', 7)
    await assignSeasonPilgrimGroup(10, 4, 7)
    await setSeasonPilgrimFamilyGroup(10, 8, 7)

    expect(apiPut).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims/10/program',
      { season_program_id: 2, reason: 'تعديل' },
      { headers: { 'X-Season-Id': '7' } },
    )
    expect(apiPut).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims/10/pilgrim-group',
      { season_pilgrim_group_id: 4 },
      { headers: { 'X-Season-Id': '7' } },
    )
    expect(apiPut).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims/10/family-group',
      { season_family_group_id: 8 },
      { headers: { 'X-Season-Id': '7' } },
    )
  })

  it('runs season pilgrim lifecycle endpoints', async () => {
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: '', data: { id: 10 } })
    vi.mocked(apiDelete).mockResolvedValue({ success: true, message: '', data: null })

    await activateSeasonPilgrim(10, 7)
    await completeSeasonPilgrim(10, 7)
    await cancelSeasonPilgrim(10, 'انسحاب', 7)
    await deleteSeasonPilgrim(10, 7)

    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims/10/activate',
      {},
      { headers: { 'X-Season-Id': '7' } },
    )
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims/10/complete',
      {},
      { headers: { 'X-Season-Id': '7' } },
    )
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/season-pilgrims/10/cancel',
      { reason: 'انسحاب' },
      { headers: { 'X-Season-Id': '7' } },
    )
    expect(apiDelete).toHaveBeenCalledWith('/api/v1/season-pilgrims/10', {
      headers: { 'X-Season-Id': '7' },
    })
  })

  it('lists and creates groups, programs and families under season context', async () => {
    vi.mocked(apiGet).mockResolvedValue({ success: true, message: '', data: [] })
    vi.mocked(apiPost).mockResolvedValue({ success: true, message: '', data: { id: 1 } })

    await listPilgrimGroups(5)
    await listSeasonPrograms(5)
    await listFamilyGroups(5)
    await createPilgrimGroup({ code: 'G1', name: 'مجموعة' }, 5)
    await createSeasonProgram({ code: 'P1', name: 'برنامج' }, 5)
    await createFamilyGroup({ name: 'عائلة' }, 5)

    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-pilgrim-groups', {
      headers: { 'X-Season-Id': '5' },
    })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-programs', {
      headers: { 'X-Season-Id': '5' },
    })
    expect(apiGet).toHaveBeenCalledWith('/api/v1/season-family-groups', {
      headers: { 'X-Season-Id': '5' },
    })
    expect(apiPost).toHaveBeenCalledWith(
      '/api/v1/season-pilgrim-groups',
      { code: 'G1', name: 'مجموعة' },
      { headers: { 'X-Season-Id': '5' } },
    )
  })
})
