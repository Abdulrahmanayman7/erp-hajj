import { apiDelete, apiGet, apiPost, apiPut } from '@/shared/api/http'

import type { RegisterSeasonPilgrimPayload } from '../types/enrollment'
import type {
  ListSeasonPilgrimsParams,
  SeasonPilgrim,
  SeasonPilgrimsListMeta,
} from '../types/seasonPilgrims'
import { seasonRequestOptions } from './seasonRequest'

function toQuery(params: ListSeasonPilgrimsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listSeasonPilgrims(
  params: ListSeasonPilgrimsParams = {},
  seasonId?: number | null,
): Promise<{
  data: SeasonPilgrim[]
  meta: SeasonPilgrimsListMeta
}> {
  const response = await apiGet<SeasonPilgrim[]>(
    `/api/v1/season-pilgrims${toQuery(params)}`,
    seasonRequestOptions(seasonId),
  )
  return {
    data: response.data,
    meta: response.meta as unknown as SeasonPilgrimsListMeta,
  }
}

export async function getSeasonPilgrim(
  id: number,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiGet<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function registerSeasonPilgrim(
  payload: RegisterSeasonPilgrimPayload,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPost<SeasonPilgrim>(
    '/api/v1/season-pilgrims',
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function changeSeasonPilgrimProgram(
  id: number,
  seasonProgramId: number | null,
  reason?: string | null,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPut<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}/program`,
    {
      season_program_id: seasonProgramId,
      reason: reason || null,
    },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function assignSeasonPilgrimGroup(
  id: number,
  seasonPilgrimGroupId: number | null,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPut<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}/pilgrim-group`,
    { season_pilgrim_group_id: seasonPilgrimGroupId },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function setSeasonPilgrimFamilyGroup(
  id: number,
  seasonFamilyGroupId: number | null,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPut<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}/family-group`,
    { season_family_group_id: seasonFamilyGroupId },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function activateSeasonPilgrim(
  id: number,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPost<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}/activate`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function completeSeasonPilgrim(
  id: number,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPost<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}/complete`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function cancelSeasonPilgrim(
  id: number,
  reason: string,
  seasonId?: number | null,
): Promise<SeasonPilgrim> {
  const response = await apiPost<SeasonPilgrim>(
    `/api/v1/season-pilgrims/${id}/cancel`,
    { reason },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deleteSeasonPilgrim(
  id: number,
  seasonId?: number | null,
): Promise<void> {
  await apiDelete(`/api/v1/season-pilgrims/${id}`, seasonRequestOptions(seasonId))
}
