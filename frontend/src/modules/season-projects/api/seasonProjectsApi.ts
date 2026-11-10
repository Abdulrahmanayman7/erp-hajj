import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  ListSeasonProjectsParams,
  PaginationMeta,
  SeasonProject,
  SeasonProjectBudget,
  SeasonProjectPhase,
  SeasonProjectPhaseFormState,
  SeasonProjectProgressBaseline,
  SeasonProjectProgressCheckpoint,
} from '../types/seasonProjects'
import { seasonRequestOptions } from './seasonRequest'

function toQuery(params: Record<string, string | number | undefined | null>): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listSeasonProjects(
  params: ListSeasonProjectsParams = {},
): Promise<{ data: SeasonProject[]; meta: PaginationMeta }> {
  const { seasonId, ...rest } = params
  const response = await apiGet<SeasonProject[]>(
    `/api/v1/season-projects${toQuery(rest as Record<string, string | number | undefined>)}`,
    seasonRequestOptions(seasonId),
  )
  return {
    data: response.data,
    meta: (response.meta as unknown as PaginationMeta) ?? {
      current_page: 1,
      per_page: 15,
      total: 0,
      last_page: 1,
    },
  }
}

export async function getSeasonProject(id: number, seasonId?: number | null): Promise<SeasonProject> {
  return (await apiGet<SeasonProject>(`/api/v1/season-projects/${id}`, seasonRequestOptions(seasonId))).data
}

export async function createSeasonProject(
  payload: { title: string; description?: string | null; owner_employee_id?: number | null },
  seasonId?: number | null,
): Promise<SeasonProject> {
  return (
    await apiPost<SeasonProject>('/api/v1/season-projects', payload, seasonRequestOptions(seasonId))
  ).data
}

export async function updateSeasonProject(
  id: number,
  payload: {
    title?: string
    description?: string | null
    owner_employee_id?: number | null
  },
  seasonId?: number | null,
): Promise<SeasonProject> {
  return (
    await apiPatch<SeasonProject>(
      `/api/v1/season-projects/${id}`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function deleteSeasonProject(id: number, seasonId?: number | null): Promise<void> {
  await apiDelete(`/api/v1/season-projects/${id}`, seasonRequestOptions(seasonId))
}

export async function activateSeasonProject(
  id: number,
  seasonId?: number | null,
): Promise<SeasonProject> {
  return (
    await apiPost<SeasonProject>(
      `/api/v1/season-projects/${id}/activate`,
      {},
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function completeSeasonProject(
  id: number,
  seasonId?: number | null,
): Promise<SeasonProject> {
  return (
    await apiPost<SeasonProject>(
      `/api/v1/season-projects/${id}/complete`,
      {},
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function cancelSeasonProject(
  id: number,
  seasonId?: number | null,
): Promise<SeasonProject> {
  return (
    await apiPost<SeasonProject>(
      `/api/v1/season-projects/${id}/cancel`,
      {},
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function reassignSeasonProjectOwner(
  id: number,
  ownerEmployeeId: number,
  seasonId?: number | null,
): Promise<SeasonProject> {
  return (
    await apiPost<SeasonProject>(
      `/api/v1/season-projects/${id}/reassign-owner`,
      { owner_employee_id: ownerEmployeeId },
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function listSeasonProjectPhases(
  projectId: number,
  seasonId?: number | null,
): Promise<SeasonProjectPhase[]> {
  return (
    await apiGet<SeasonProjectPhase[]>(
      `/api/v1/season-projects/${projectId}/phases`,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function createSeasonProjectPhase(
  projectId: number,
  payload: Partial<SeasonProjectPhaseFormState> & { title: string },
  seasonId?: number | null,
): Promise<SeasonProjectPhase> {
  return (
    await apiPost<SeasonProjectPhase>(
      `/api/v1/season-projects/${projectId}/phases`,
      {
        title: payload.title,
        sort_order: payload.sort_order === '' || payload.sort_order == null ? undefined : Number(payload.sort_order),
        starts_on: payload.starts_on || null,
        ends_on: payload.ends_on || null,
        status: payload.status,
      },
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function updateSeasonProjectPhase(
  projectId: number,
  phaseId: number,
  payload: Partial<SeasonProjectPhaseFormState>,
  seasonId?: number | null,
): Promise<SeasonProjectPhase> {
  return (
    await apiPatch<SeasonProjectPhase>(
      `/api/v1/season-projects/${projectId}/phases/${phaseId}`,
      {
        title: payload.title,
        sort_order:
          payload.sort_order === '' || payload.sort_order == null
            ? undefined
            : Number(payload.sort_order),
        starts_on: payload.starts_on === undefined ? undefined : payload.starts_on || null,
        ends_on: payload.ends_on === undefined ? undefined : payload.ends_on || null,
        status: payload.status,
      },
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function deleteSeasonProjectPhase(
  projectId: number,
  phaseId: number,
  seasonId?: number | null,
): Promise<void> {
  await apiDelete(
    `/api/v1/season-projects/${projectId}/phases/${phaseId}`,
    seasonRequestOptions(seasonId),
  )
}

export async function getSeasonProjectBudget(
  projectId: number,
  seasonId?: number | null,
): Promise<SeasonProjectBudget> {
  return (
    await apiGet<SeasonProjectBudget>(
      `/api/v1/season-projects/${projectId}/budget`,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function updateSeasonProjectBudget(
  projectId: number,
  payload: { budget_amount?: number | string | null; currency_code?: string },
  seasonId?: number | null,
): Promise<SeasonProjectBudget> {
  return (
    await apiPatch<SeasonProjectBudget>(
      `/api/v1/season-projects/${projectId}/budget`,
      payload,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function linkSeasonProjectPurchaseOrder(
  projectId: number,
  purchaseOrderId: number,
  notes?: string | null,
  seasonId?: number | null,
): Promise<SeasonProjectBudget> {
  return (
    await apiPost<SeasonProjectBudget>(
      `/api/v1/season-projects/${projectId}/budget/po-links`,
      { purchase_order_id: purchaseOrderId, notes: notes || undefined },
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function unlinkSeasonProjectPurchaseOrder(
  projectId: number,
  linkId: number,
  seasonId?: number | null,
): Promise<SeasonProjectBudget> {
  return (
    await apiDelete<SeasonProjectBudget>(
      `/api/v1/season-projects/${projectId}/budget/po-links/${linkId}`,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function listSeasonProjectProgressBaselines(
  projectId: number,
  seasonId?: number | null,
): Promise<SeasonProjectProgressBaseline[]> {
  return (
    await apiGet<SeasonProjectProgressBaseline[]>(
      `/api/v1/season-projects/${projectId}/progress-baselines`,
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function upsertSeasonProjectProgressBaselineDraft(
  projectId: number,
  checkpoints: Array<Pick<SeasonProjectProgressCheckpoint, 'effective_at' | 'planned_progress_percent'>>,
  seasonId?: number | null,
): Promise<{ id: number; version: number; status: string }> {
  return (
    await apiPost<{ id: number; version: number; status: string }>(
      `/api/v1/season-projects/${projectId}/progress-baselines`,
      { checkpoints },
      seasonRequestOptions(seasonId),
    )
  ).data
}

export async function approveSeasonProjectProgressBaseline(
  projectId: number,
  baselineId: number,
  seasonId?: number | null,
): Promise<{ id: number; version: number; status: string; approved_at: string | null }> {
  return (
    await apiPost<{ id: number; version: number; status: string; approved_at: string | null }>(
      `/api/v1/season-projects/${projectId}/progress-baselines/${baselineId}/approve`,
      {},
      seasonRequestOptions(seasonId),
    )
  ).data
}
