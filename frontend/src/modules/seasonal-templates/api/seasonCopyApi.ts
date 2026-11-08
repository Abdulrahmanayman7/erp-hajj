import { apiGet, apiPost } from '@/shared/api/http'

import type {
  CreateSeasonCopyPreviewPayload,
  ExecuteSeasonCopyPayload,
  ListSeasonCopyRunsParams,
  SeasonCopyListMeta,
  SeasonCopyPreview,
  SeasonCopyRun,
} from '../types/seasonCopy'

function toQuery(params: ListSeasonCopyRunsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

/** Season ids travel in the body — these routes run outside the season context (no X-Season-Id). */
export async function createSeasonCopyPreview(
  payload: CreateSeasonCopyPreviewPayload,
): Promise<SeasonCopyPreview> {
  return (await apiPost<SeasonCopyPreview>('/api/v1/season-copy/previews', payload)).data
}

export async function getSeasonCopyPreview(id: number): Promise<SeasonCopyPreview> {
  return (await apiGet<SeasonCopyPreview>(`/api/v1/season-copy/previews/${id}`)).data
}

export async function executeSeasonCopy(payload: ExecuteSeasonCopyPayload): Promise<SeasonCopyRun> {
  return (await apiPost<SeasonCopyRun>('/api/v1/season-copy/executions', payload)).data
}

export async function listSeasonCopyRuns(
  params: ListSeasonCopyRunsParams = {},
): Promise<{ data: SeasonCopyRun[]; meta: SeasonCopyListMeta }> {
  const response = await apiGet<SeasonCopyRun[]>(`/api/v1/season-copy/runs${toQuery(params)}`)
  return { data: response.data, meta: response.meta as unknown as SeasonCopyListMeta }
}

export async function getSeasonCopyRun(id: number): Promise<SeasonCopyRun> {
  return (await apiGet<SeasonCopyRun>(`/api/v1/season-copy/runs/${id}`)).data
}
