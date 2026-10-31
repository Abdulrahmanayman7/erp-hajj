import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateSeasonProgramPayload,
  SeasonProgram,
  UpdateSeasonProgramPayload,
} from '../types/programs'
import { seasonRequestOptions } from './seasonRequest'

export async function listSeasonPrograms(seasonId?: number | null): Promise<SeasonProgram[]> {
  const response = await apiGet<SeasonProgram[]>(
    '/api/v1/season-programs',
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function createSeasonProgram(
  payload: CreateSeasonProgramPayload,
  seasonId?: number | null,
): Promise<SeasonProgram> {
  const response = await apiPost<SeasonProgram>(
    '/api/v1/season-programs',
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function updateSeasonProgram(
  id: number,
  payload: UpdateSeasonProgramPayload,
  seasonId?: number | null,
): Promise<SeasonProgram> {
  const response = await apiPatch<SeasonProgram>(
    `/api/v1/season-programs/${id}`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function activateSeasonProgram(
  id: number,
  seasonId?: number | null,
): Promise<SeasonProgram> {
  const response = await apiPost<SeasonProgram>(
    `/api/v1/season-programs/${id}/activate`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deactivateSeasonProgram(
  id: number,
  seasonId?: number | null,
): Promise<SeasonProgram> {
  const response = await apiPost<SeasonProgram>(
    `/api/v1/season-programs/${id}/deactivate`,
    {},
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deleteSeasonProgram(
  id: number,
  seasonId?: number | null,
): Promise<void> {
  await apiDelete(`/api/v1/season-programs/${id}`, seasonRequestOptions(seasonId))
}
