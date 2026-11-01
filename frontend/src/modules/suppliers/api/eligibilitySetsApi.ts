import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateEligibilitySetPayload,
  EligibilityRequirementSet,
  EligibilitySetsListMeta,
  ListEligibilitySetsParams,
  UpdateEligibilitySetPayload,
} from '../types/eligibility'

const BASE = '/api/v1/supplier-eligibility-requirement-sets'

function toQuery(params: ListEligibilitySetsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listEligibilitySets(params: ListEligibilitySetsParams = {}): Promise<{
  data: EligibilityRequirementSet[]
  meta: EligibilitySetsListMeta
}> {
  const response = await apiGet<EligibilityRequirementSet[]>(`${BASE}${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as EligibilitySetsListMeta,
  }
}

export async function getEligibilitySet(id: number): Promise<EligibilityRequirementSet> {
  const response = await apiGet<EligibilityRequirementSet>(`${BASE}/${id}`)
  return response.data
}

export async function createEligibilitySet(
  payload: CreateEligibilitySetPayload,
): Promise<EligibilityRequirementSet> {
  const response = await apiPost<EligibilityRequirementSet>(BASE, payload)
  return response.data
}

export async function updateEligibilitySet(
  id: number,
  payload: UpdateEligibilitySetPayload,
): Promise<EligibilityRequirementSet> {
  const response = await apiPatch<EligibilityRequirementSet>(`${BASE}/${id}`, payload)
  return response.data
}

export async function approveEligibilitySet(
  id: number,
  comment?: string | null,
): Promise<EligibilityRequirementSet> {
  const body = comment && comment.trim() ? { comment: comment.trim() } : {}
  const response = await apiPost<EligibilityRequirementSet>(`${BASE}/${id}/approve`, body)
  return response.data
}

export async function newDraftFromEligibilitySet(id: number): Promise<EligibilityRequirementSet> {
  const response = await apiPost<EligibilityRequirementSet>(`${BASE}/${id}/new-draft`, {})
  return response.data
}
