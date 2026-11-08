import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateSeasonTemplatePayload,
  ListSeasonTemplatesParams,
  SeasonTemplate,
  SeasonTemplatesListMeta,
  UpdateSeasonTemplatePayload,
} from '../types/seasonTemplates'

function toQuery(params: ListSeasonTemplatesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '' || value === 'all') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listSeasonTemplates(
  params: ListSeasonTemplatesParams = {},
): Promise<{ data: SeasonTemplate[]; meta: SeasonTemplatesListMeta }> {
  const response = await apiGet<SeasonTemplate[]>(`/api/v1/season-templates${toQuery(params)}`)
  return { data: response.data, meta: response.meta as unknown as SeasonTemplatesListMeta }
}

export async function getSeasonTemplate(id: number): Promise<SeasonTemplate> {
  return (await apiGet<SeasonTemplate>(`/api/v1/season-templates/${id}`)).data
}

export async function createSeasonTemplate(
  payload: CreateSeasonTemplatePayload,
): Promise<SeasonTemplate> {
  return (await apiPost<SeasonTemplate>('/api/v1/season-templates', payload)).data
}

export async function updateSeasonTemplate(
  id: number,
  payload: UpdateSeasonTemplatePayload,
): Promise<SeasonTemplate> {
  return (await apiPatch<SeasonTemplate>(`/api/v1/season-templates/${id}`, payload)).data
}

export async function approveSeasonTemplate(id: number): Promise<SeasonTemplate> {
  return (await apiPost<SeasonTemplate>(`/api/v1/season-templates/${id}/approve`, {})).data
}

export async function archiveSeasonTemplate(id: number): Promise<SeasonTemplate> {
  return (await apiPost<SeasonTemplate>(`/api/v1/season-templates/${id}/archive`, {})).data
}
