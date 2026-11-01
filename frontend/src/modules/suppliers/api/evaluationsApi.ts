import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateEvaluationPayload,
  CriteriaPackSummary,
  EvaluationListFilters,
  SupplierEvaluation,
  SupplierEvaluationAppeal,
  UpdateEvaluationPayload,
} from '../types/evaluations'

const base = '/api/v1/supplier-evaluations'

export async function listSupplierEvaluations(
  filters: EvaluationListFilters = {},
): Promise<{ data: SupplierEvaluation[]; meta: Record<string, number> }> {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  })
  const qs = params.toString()
  const response = await apiGet<SupplierEvaluation[]>(`${base}${qs ? `?${qs}` : ''}`)
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function getSupplierEvaluation(id: number): Promise<SupplierEvaluation> {
  const response = await apiGet<SupplierEvaluation>(`${base}/${id}`)
  return response.data
}

export async function createSupplierEvaluation(
  payload: CreateEvaluationPayload,
): Promise<SupplierEvaluation> {
  const response = await apiPost<SupplierEvaluation>(base, payload)
  return response.data
}

export async function updateSupplierEvaluation(
  id: number,
  payload: UpdateEvaluationPayload,
): Promise<SupplierEvaluation> {
  const response = await apiPatch<SupplierEvaluation>(`${base}/${id}`, payload)
  return response.data
}

export async function submitSupplierEvaluation(id: number): Promise<SupplierEvaluation> {
  const response = await apiPost<SupplierEvaluation>(`${base}/${id}/submit`, {})
  return response.data
}

export async function approveSupplierEvaluation(
  id: number,
  monitoringRecommended?: boolean,
): Promise<SupplierEvaluation> {
  const body =
    monitoringRecommended === undefined ? {} : { monitoring_recommended: monitoringRecommended }
  const response = await apiPost<SupplierEvaluation>(`${base}/${id}/approve`, body)
  return response.data
}

export async function rejectSupplierEvaluation(
  id: number,
  reason: string,
): Promise<SupplierEvaluation> {
  const response = await apiPost<SupplierEvaluation>(`${base}/${id}/reject`, { reason })
  return response.data
}

export async function listEvaluationAppeals(evaluationId: number): Promise<SupplierEvaluationAppeal[]> {
  const response = await apiGet<SupplierEvaluationAppeal[]>(`${base}/${evaluationId}/appeals`)
  return response.data
}

export async function fileEvaluationAppeal(
  evaluationId: number,
  reason: string,
): Promise<SupplierEvaluationAppeal> {
  const response = await apiPost<SupplierEvaluationAppeal>(`${base}/${evaluationId}/appeals`, {
    reason,
  })
  return response.data
}

export async function acceptEvaluationAppeal(appealId: number): Promise<SupplierEvaluationAppeal> {
  const response = await apiPost<SupplierEvaluationAppeal>(
    `/api/v1/supplier-evaluation-appeals/${appealId}/accept`,
    {},
  )
  return response.data
}

export async function rejectEvaluationAppeal(
  appealId: number,
  reviewOutcomeNote?: string,
): Promise<SupplierEvaluationAppeal> {
  const response = await apiPost<SupplierEvaluationAppeal>(
    `/api/v1/supplier-evaluation-appeals/${appealId}/reject`,
    { review_outcome_note: reviewOutcomeNote ?? null },
  )
  return response.data
}

export async function listPublishedCriteriaPacks(
  supplierCategoryId: number,
): Promise<CriteriaPackSummary[]> {
  const response = await apiGet<CriteriaPackSummary[]>(
    `/api/v1/supplier-evaluation-criteria-packs?supplier_category_id=${supplierCategoryId}&status=published`,
  )
  return response.data
}

export async function getCriteriaPack(packId: number): Promise<CriteriaPackSummary> {
  const response = await apiGet<CriteriaPackSummary>(
    `/api/v1/supplier-evaluation-criteria-packs/${packId}`,
  )
  return response.data
}
