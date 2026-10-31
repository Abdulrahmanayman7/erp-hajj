import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  CreateFamilyGroupPayload,
  SeasonFamilyGroup,
  UpdateFamilyGroupPayload,
} from '../types/families'
import { seasonRequestOptions } from './seasonRequest'

export async function listFamilyGroups(seasonId?: number | null): Promise<SeasonFamilyGroup[]> {
  const response = await apiGet<SeasonFamilyGroup[]>(
    '/api/v1/season-family-groups',
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function getFamilyGroup(
  id: number,
  seasonId?: number | null,
): Promise<SeasonFamilyGroup> {
  const response = await apiGet<SeasonFamilyGroup>(
    `/api/v1/season-family-groups/${id}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function createFamilyGroup(
  payload: CreateFamilyGroupPayload,
  seasonId?: number | null,
): Promise<SeasonFamilyGroup> {
  const response = await apiPost<SeasonFamilyGroup>(
    '/api/v1/season-family-groups',
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function updateFamilyGroup(
  id: number,
  payload: UpdateFamilyGroupPayload,
  seasonId?: number | null,
): Promise<SeasonFamilyGroup> {
  const response = await apiPatch<SeasonFamilyGroup>(
    `/api/v1/season-family-groups/${id}`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function deleteFamilyGroup(id: number, seasonId?: number | null): Promise<void> {
  await apiDelete(`/api/v1/season-family-groups/${id}`, seasonRequestOptions(seasonId))
}

export async function setFamilyPrimaryMember(
  familyGroupId: number,
  seasonPilgrimId: number,
  seasonId?: number | null,
): Promise<SeasonFamilyGroup> {
  const response = await apiPut<SeasonFamilyGroup>(
    `/api/v1/season-family-groups/${familyGroupId}/primary-member`,
    { season_pilgrim_id: seasonPilgrimId },
    seasonRequestOptions(seasonId),
  )
  return response.data
}
