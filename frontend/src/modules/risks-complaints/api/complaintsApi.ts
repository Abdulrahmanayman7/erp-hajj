import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  ComplaintAssignPayload,
  ComplaintClosePayload,
  ComplaintCreatePayload,
  ComplaintEscalatePayload,
  ComplaintNotePayload,
  ComplaintReopenPayload,
  ComplaintResolvePayload,
  ComplaintsListMeta,
  ComplaintUpdatePayload,
  ListComplaintsParams,
  OperationalComplaint,
} from '../types/complaints'
import { seasonRequestOptions } from './seasonRequest'

function query(params: ListComplaintsParams): string {
  const value = new URLSearchParams()
  Object.entries(params).forEach(([key, item]) => {
    if (key === 'seasonId') return
    if (item === undefined || item === null || item === '') return
    value.set(key, String(item))
  })
  return value.size ? `?${value}` : ''
}

export async function listComplaints(
  params: ListComplaintsParams = {},
): Promise<{ data: OperationalComplaint[]; meta: ComplaintsListMeta }> {
  const response = await apiGet<OperationalComplaint[]>(
    `/api/v1/complaints${query(params)}`,
    seasonRequestOptions(params.seasonId),
  )
  return { data: response.data, meta: response.meta as unknown as ComplaintsListMeta }
}

export async function getComplaint(
  id: number,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiGet<OperationalComplaint>(`/api/v1/complaints/${id}`, seasonRequestOptions(seasonId))
  ).data
}

export async function createComplaint(
  payload: ComplaintCreatePayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      '/api/v1/complaints',
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function updateComplaint(
  id: number,
  payload: ComplaintUpdatePayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPatch<OperationalComplaint>(
      `/api/v1/complaints/${id}`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function assignComplaint(
  id: number,
  payload: ComplaintAssignPayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      `/api/v1/complaints/${id}/assign`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function escalateComplaint(
  id: number,
  payload: ComplaintEscalatePayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      `/api/v1/complaints/${id}/escalate`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function resolveComplaint(
  id: number,
  payload: ComplaintResolvePayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      `/api/v1/complaints/${id}/resolve`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function closeComplaint(
  id: number,
  payload: ComplaintClosePayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      `/api/v1/complaints/${id}/close`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function reopenComplaint(
  id: number,
  payload: ComplaintReopenPayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      `/api/v1/complaints/${id}/reopen`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function addComplaintNote(
  id: number,
  payload: ComplaintNotePayload,
  seasonId?: number | null,
): Promise<OperationalComplaint> {
  return (
    await apiPost<OperationalComplaint>(
      `/api/v1/complaints/${id}/notes`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function deleteComplaint(id: number, seasonId?: number | null): Promise<void> {
  await apiDelete(`/api/v1/complaints/${id}`, seasonRequestOptions(seasonId))
}

export async function getComplaintTimeline(
  id: number,
  seasonId?: number | null,
): Promise<
  Array<{
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
  }>
> {
  return (
    await apiGet<
      Array<{
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
      }>
    >(`/api/v1/complaints/${id}/timeline`, seasonRequestOptions(seasonId))
  ).data
}
