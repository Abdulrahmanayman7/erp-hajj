import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  ListRisksParams,
  OperationalRisk,
  RiskAssessPayload,
  RiskAssignPayload,
  RiskClosePayload,
  RiskCreatePayload,
  RisksListMeta,
  RiskTreatmentPayload,
  RiskUpdatePayload,
} from '../types/risks'
import { seasonRequestOptions } from './seasonRequest'

function query(params: ListRisksParams): string {
  const value = new URLSearchParams()
  Object.entries(params).forEach(([key, item]) => {
    if (key === 'seasonId') return
    if (item === undefined || item === null || item === '') return
    value.set(key, String(item))
  })
  return value.size ? `?${value}` : ''
}

export async function listRisks(
  params: ListRisksParams = {},
): Promise<{ data: OperationalRisk[]; meta: RisksListMeta }> {
  const response = await apiGet<OperationalRisk[]>(
    `/api/v1/risks${query(params)}`,
    seasonRequestOptions(params.seasonId),
  )
  return { data: response.data, meta: response.meta as unknown as RisksListMeta }
}

export async function getRisk(id: number, seasonId?: number | null): Promise<OperationalRisk> {
  return (await apiGet<OperationalRisk>(`/api/v1/risks/${id}`, seasonRequestOptions(seasonId))).data
}

export async function createRisk(
  payload: RiskCreatePayload,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPost<OperationalRisk>('/api/v1/risks', payload, seasonRequestOptions(seasonId))
  ).data
}

export async function updateRisk(
  id: number,
  payload: RiskUpdatePayload,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPatch<OperationalRisk>(`/api/v1/risks/${id}`, payload, seasonRequestOptions(seasonId))
  ).data
}

export async function assessRisk(
  id: number,
  payload: RiskAssessPayload,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPost<OperationalRisk>(
      `/api/v1/risks/${id}/assess`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function upsertRiskTreatment(
  id: number,
  payload: RiskTreatmentPayload,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPut<OperationalRisk>(
      `/api/v1/risks/${id}/treatment`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function approveRiskAssessment(
  id: number,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPost<OperationalRisk>(
      `/api/v1/risks/${id}/approve-assessment`,
      {},
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function assignRisk(
  id: number,
  payload: RiskAssignPayload,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPost<OperationalRisk>(
      `/api/v1/risks/${id}/assign`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function closeRisk(
  id: number,
  payload: RiskClosePayload,
  seasonId?: number | null,
): Promise<OperationalRisk> {
  return (
    await apiPost<OperationalRisk>(
      `/api/v1/risks/${id}/close`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function deleteRisk(id: number, seasonId?: number | null): Promise<void> {
  await apiDelete(`/api/v1/risks/${id}`, seasonRequestOptions(seasonId))
}

export interface OperationalTimelineEntry {
  id: number
  event_type: string
  actor_label: string | null
  actor_user_id: number | null
  entity_type: string | null
  entity_id: number | null
  entity_number: string | null
  correlation_id: string
  created_at: string | null
  summary: Record<string, unknown> | null
}

export async function getRiskTimeline(
  id: number,
  seasonId?: number | null,
): Promise<OperationalTimelineEntry[]> {
  return (
    await apiGet<OperationalTimelineEntry[]>(
      `/api/v1/risks/${id}/timeline`,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function getOperationalLinksCapabilities(seasonId?: number | null): Promise<{
  season_projects_available: boolean
  season_projects_reason: string | null
}> {
  return (
    await apiGet<{
      season_projects_available: boolean
      season_projects_reason: string | null
    }>('/api/v1/operational-links/capabilities', seasonRequestOptions(seasonId))
  ).data
}
