import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateSeasonReminderRulePayload,
  ListSeasonReminderRulesParams,
  SeasonReminderRule,
  SeasonReminderRulesListMeta,
  UpdateSeasonReminderRulePayload,
} from '../types/seasonReminderRules'

function toQuery(params: ListSeasonReminderRulesParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '' || value === 'all') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listSeasonReminderRules(
  seasonId: number,
  params: ListSeasonReminderRulesParams = {},
): Promise<{ data: SeasonReminderRule[]; meta: SeasonReminderRulesListMeta }> {
  const response = await apiGet<SeasonReminderRule[]>(
    `/api/v1/seasons/${seasonId}/reminder-rules${toQuery(params)}`,
  )
  return { data: response.data, meta: response.meta as unknown as SeasonReminderRulesListMeta }
}

export async function getSeasonReminderRule(id: number): Promise<SeasonReminderRule> {
  return (await apiGet<SeasonReminderRule>(`/api/v1/season-reminder-rules/${id}`)).data
}

export async function createSeasonReminderRule(
  seasonId: number,
  payload: CreateSeasonReminderRulePayload,
): Promise<SeasonReminderRule> {
  return (await apiPost<SeasonReminderRule>(`/api/v1/seasons/${seasonId}/reminder-rules`, payload)).data
}

export async function updateSeasonReminderRule(
  id: number,
  payload: UpdateSeasonReminderRulePayload,
): Promise<SeasonReminderRule> {
  return (await apiPatch<SeasonReminderRule>(`/api/v1/season-reminder-rules/${id}`, payload)).data
}

export async function activateSeasonReminderRule(id: number): Promise<SeasonReminderRule> {
  return (await apiPost<SeasonReminderRule>(`/api/v1/season-reminder-rules/${id}/activate`, {})).data
}

export async function pauseSeasonReminderRule(id: number): Promise<SeasonReminderRule> {
  return (await apiPost<SeasonReminderRule>(`/api/v1/season-reminder-rules/${id}/pause`, {})).data
}

export async function archiveSeasonReminderRule(id: number): Promise<SeasonReminderRule> {
  return (await apiPost<SeasonReminderRule>(`/api/v1/season-reminder-rules/${id}/archive`, {})).data
}
