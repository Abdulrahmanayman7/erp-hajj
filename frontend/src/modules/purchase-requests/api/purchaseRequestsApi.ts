import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  CreatePurchaseRequestPayload,
  DuplicateHint,
  FinancialReviewSummary,
  PurchaseApprovalDelegation,
  PurchaseApprovalMatrix,
  PurchaseApprovalSlaPolicy,
  PurchaseApproverAuthority,
  PurchaseRequest,
  PurchaseRequestListFilters,
  PurchaseRequestVersion,
  UpdatePurchaseRequestPayload,
} from '../types/purchaseRequests'

const base = '/api/v1/purchase-requests'

function toQuery(filters: PurchaseRequestListFilters = {}): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  })
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function listPurchaseRequests(
  filters: PurchaseRequestListFilters = {},
): Promise<{ data: PurchaseRequest[]; meta: Record<string, number> }> {
  const response = await apiGet<PurchaseRequest[]>(`${base}${toQuery(filters)}`)
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function getPurchaseRequest(id: number): Promise<PurchaseRequest> {
  const response = await apiGet<PurchaseRequest>(`${base}/${id}`)
  return response.data
}

export async function createPurchaseRequest(
  payload: CreatePurchaseRequestPayload,
): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(base, payload)
  return response.data
}

export async function updatePurchaseRequest(
  id: number,
  payload: UpdatePurchaseRequestPayload,
): Promise<PurchaseRequest> {
  const response = await apiPatch<PurchaseRequest>(`${base}/${id}`, payload)
  return response.data
}

export async function replacePurchaseRequestLines(
  id: number,
  lines: CreatePurchaseRequestPayload['lines'],
): Promise<PurchaseRequest> {
  const response = await apiPut<PurchaseRequest>(`${base}/${id}/lines`, { lines })
  return response.data
}

export async function submitPurchaseRequest(
  id: number,
  acknowledgeDuplicateWarning = false,
  comment?: string,
): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/submit`, {
    acknowledge_duplicate_warning: acknowledgeDuplicateWarning,
    comment: comment ?? null,
  })
  return response.data
}

export async function cancelPurchaseRequest(id: number, reason: string): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/cancel`, { reason })
  return response.data
}

export async function verifyPurchaseRequestPriority(id: number): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/verify-priority`, {})
  return response.data
}

export async function recordFinancialReview(
  id: number,
  payload: { outcome: string; justification: string; version_no?: number },
): Promise<FinancialReviewSummary> {
  const response = await apiPost<FinancialReviewSummary>(`${base}/${id}/financial-reviews`, payload)
  return response.data
}

export async function listFinancialReviews(id: number): Promise<FinancialReviewSummary[]> {
  const response = await apiGet<FinancialReviewSummary[]>(`${base}/${id}/financial-reviews`)
  return response.data
}

export async function listPurchaseRequestVersions(
  id: number,
  includeLines = false,
): Promise<PurchaseRequestVersion[]> {
  const qs = includeLines ? '?include=lines' : ''
  const response = await apiGet<PurchaseRequestVersion[]>(`${base}/${id}/versions${qs}`)
  return response.data
}

export async function getDuplicateHints(purchaseRequestId: number): Promise<DuplicateHint[]> {
  const response = await apiGet<DuplicateHint[]>(
    `${base}/duplicate-hints?purchase_request_id=${purchaseRequestId}`,
  )
  return response.data
}

export async function getDuplicateHintsForDraft(payload: {
  season_id?: number | null
  lines: Array<{ line_no?: number; description: string; need_date: string }>
}): Promise<DuplicateHint[]> {
  const params = new URLSearchParams()
  if (payload.season_id != null) params.set('season_id', String(payload.season_id))
  params.set('lines', JSON.stringify(payload.lines))
  const response = await apiGet<DuplicateHint[]>(`${base}/duplicate-hints?${params.toString()}`)
  return response.data
}

export async function listApprovalInbox(): Promise<PurchaseRequest[]> {
  const response = await apiGet<PurchaseRequest[]>(`${base}/approval-inbox`)
  return response.data
}

export async function approvePurchaseRequest(
  id: number,
  comment?: string,
): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/approve`, {
    comment: comment ?? null,
  })
  return response.data
}

export async function rejectPurchaseRequest(id: number, reason: string): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/reject`, { reason })
  return response.data
}

export async function returnPurchaseRequest(id: number, reason: string): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/return`, { reason })
  return response.data
}

export async function cancelApprovedPurchaseRequest(
  id: number,
  reason: string,
): Promise<PurchaseRequest> {
  const response = await apiPost<PurchaseRequest>(`${base}/${id}/cancel-approved`, { reason })
  return response.data
}

export async function listPurchaseApprovalMatrices(): Promise<{
  data: PurchaseApprovalMatrix[]
  meta: Record<string, number>
}> {
  const response = await apiGet<PurchaseApprovalMatrix[]>('/api/v1/purchase-approval-matrices')
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function createPurchaseApprovalMatrix(name: string): Promise<PurchaseApprovalMatrix> {
  const response = await apiPost<PurchaseApprovalMatrix>('/api/v1/purchase-approval-matrices', {
    name,
  })
  return response.data
}

export async function publishPurchaseApprovalMatrix(id: number): Promise<PurchaseApprovalMatrix> {
  const response = await apiPost<PurchaseApprovalMatrix>(
    `/api/v1/purchase-approval-matrices/${id}/publish`,
    {},
  )
  return response.data
}

export async function listPurchaseApproverAuthorities(): Promise<{
  data: PurchaseApproverAuthority[]
  meta: Record<string, number>
}> {
  const response = await apiGet<PurchaseApproverAuthority[]>(
    '/api/v1/purchase-approver-authorities?active_only=1',
  )
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function grantPurchaseApproverAuthority(payload: {
  user_id: number
  max_amount: number
  covers_unpriced: boolean
  effective_from: string
  effective_to?: string | null
  organization_unit_id?: number | null
}): Promise<PurchaseApproverAuthority> {
  const response = await apiPost<PurchaseApproverAuthority>(
    '/api/v1/purchase-approver-authorities',
    payload,
  )
  return response.data
}

export async function listPurchaseApprovalDelegations(): Promise<{
  data: PurchaseApprovalDelegation[]
  meta: Record<string, number>
}> {
  const response = await apiGet<PurchaseApprovalDelegation[]>('/api/v1/purchase-approval-delegations')
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function listPurchaseApprovalSlaPolicies(): Promise<PurchaseApprovalSlaPolicy[]> {
  const response = await apiGet<PurchaseApprovalSlaPolicy[]>('/api/v1/purchase-approval-sla-policies')
  return response.data
}

export async function upsertPurchaseApprovalSlaPolicy(payload: {
  priority: string
  level_order: number
  target_hours: number
  reminder_offset_hours?: number | null
}): Promise<PurchaseApprovalSlaPolicy> {
  const response = await apiPut<PurchaseApprovalSlaPolicy>(
    '/api/v1/purchase-approval-sla-policies',
    payload,
  )
  return response.data
}

export async function deletePurchaseApprovalSlaPolicy(id: number): Promise<void> {
  await apiDelete(`/api/v1/purchase-approval-sla-policies/${id}`)
}
