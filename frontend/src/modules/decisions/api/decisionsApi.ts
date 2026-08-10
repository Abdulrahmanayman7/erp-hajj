import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'
import type { Decision, DecisionPayload, DecisionsListMeta, ListDecisionsParams } from '../types/decisions'

function query(params: ListDecisionsParams): string {
  const value = new URLSearchParams()
  Object.entries(params).forEach(([key, item]) => {
    if (item === undefined || item === null || item === '') return
    value.set(key, typeof item === 'boolean' ? (item ? '1' : '0') : String(item))
  })
  return value.size ? `?${value}` : ''
}

export async function listDecisions(params: ListDecisionsParams = {}): Promise<{ data: Decision[]; meta: DecisionsListMeta }> {
  const response = await apiGet<Decision[]>(`/api/v1/decisions${query(params)}`)
  return { data: response.data, meta: response.meta as unknown as DecisionsListMeta }
}
export async function getDecision(id: number): Promise<Decision> { return (await apiGet<Decision>(`/api/v1/decisions/${id}`)).data }
export async function createDecision(payload: DecisionPayload): Promise<Decision> { return (await apiPost<Decision>('/api/v1/decisions', payload)).data }
export async function updateDecision(id: number, payload: DecisionPayload): Promise<Decision> { return (await apiPatch<Decision>(`/api/v1/decisions/${id}`, payload)).data }
export async function deleteDecision(id: number): Promise<void> { await apiDelete(`/api/v1/decisions/${id}`) }
export async function submitDecision(id: number): Promise<Decision> { return (await apiPost<Decision>(`/api/v1/decisions/${id}/submit`, {})).data }
export async function returnDecisionToDraft(id: number, comment: string): Promise<Decision> { return (await apiPost<Decision>(`/api/v1/decisions/${id}/return-draft`, { comment })).data }
export async function approveDecision(id: number): Promise<Decision> { return (await apiPost<Decision>(`/api/v1/decisions/${id}/approve`, {})).data }
export async function cancelDecision(id: number): Promise<Decision> { return (await apiPost<Decision>(`/api/v1/decisions/${id}/cancel`, {})).data }
export async function closeDecision(id: number): Promise<Decision> { return (await apiPost<Decision>(`/api/v1/decisions/${id}/close`, {})).data }
