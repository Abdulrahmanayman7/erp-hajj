import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreatePilgrimGroupPayload,
  SeasonPilgrimGroup,
  UpdatePilgrimGroupPayload,
} from '../types/pilgrimGroups'
import { seasonRequestOptions } from './seasonRequest'

export async function listPilgrimGroups(
  seasonId?: number | null,
): Promise<SeasonPilgrimGroup[]> {
  const response = await apiGet<SeasonPilgrimGroup[]>(
    '/api/v1/season-pilgrim-groups',
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function getPilgrimGroup(
  id: number,
  seasonId?: number | null,
): Promise<SeasonPilgrimGroup> {
  const response = await apiGet<SeasonPilgrimGroup>(
    `/api/v1/season-pilgrim-groups/${id}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function createPilgrimGroup(
  payload: CreatePilgrimGroupPayload,
  seasonId?: number | null,
): Promise<SeasonPilgrimGroup> {
  const response = await apiPost<SeasonPilgrimGroup>(
    '/api/v1/season-pilgrim-groups',
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function updatePilgrimGroup(
  id: number,
  payload: UpdatePilgrimGroupPayload,
  seasonId?: number | null,
): Promise<SeasonPilgrimGroup> {
  const response = await apiPatch<SeasonPilgrimGroup>(
    `/api/v1/season-pilgrim-groups/${id}`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deletePilgrimGroup(
  id: number,
  seasonId?: number | null,
): Promise<void> {
  await apiDelete(`/api/v1/season-pilgrim-groups/${id}`, seasonRequestOptions(seasonId))
}
