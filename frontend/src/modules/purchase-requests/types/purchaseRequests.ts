export type PurchaseRequestStatus =
  | 'draft'
  | 'submitted'
  | 'under_review'
  | 'returned'
  | 'approved'
  | 'rejected'
  | 'cancelled'

export type PurchaseRequestKind = 'seasonal' | 'administrative'
export type PurchaseRequestPriority = 'normal' | 'urgent' | 'emergency'
export type FinancialReviewOutcome =
  | 'cleared'
  | 'cleared_with_conditions'
  | 'not_cleared'

export interface PurchaseRequestActorRef {
  id: number
  name: string
}

export interface PurchaseRequestLine {
  id?: number
  line_no: number
  description: string
  specifications?: string | null
  quantity: string | number
  unit: string
  need_date: string
  inventory_item_id?: number | null
  unit_estimate?: string | number | null
  line_estimate?: string | number | null
  unpriced_reason?: string | null
}

export interface FinancialReviewSummary {
  id: number
  version_no: number
  outcome: FinancialReviewOutcome
  justification: string
  reviewer_id: number
  reviewed_at: string | null
}

export type ApprovalStepStatus =
  | 'pending'
  | 'approved'
  | 'rejected'
  | 'returned'
  | 'cancelled'

export interface PurchaseRequestApprovalStep {
  id: number
  level_order: number
  status: ApprovalStepStatus | string
  due_at: string | null
  escalated_at: string | null
  acted_by: number | null
  acted_at: string | null
  comment: string | null
  decision_reason: string | null
}

export interface PurchaseRequestApprovalInstance {
  id: number
  version_no: number
  matrix_id: number | null
  matrix_version_no: number
  rule_id: number | null
  status: string
  routed_at: string | null
  steps?: PurchaseRequestApprovalStep[]
}

export interface PurchaseRequest {
  id: number
  pr_number: string
  version_no: number
  status: PurchaseRequestStatus
  pending_approval_engine: boolean
  request_kind: PurchaseRequestKind
  season_id: number | null
  title: string
  purpose: string
  priority: PurchaseRequestPriority
  urgency_justification: string | null
  priority_verified_at: string | null
  priority_verified_by: PurchaseRequestActorRef | null
  requested_by: PurchaseRequestActorRef | null
  beneficiary_organization_unit_id: number
  optional_supplier_id: number | null
  currency: string
  estimated_total_priced: string
  has_unpriced_lines: boolean
  matrix_id: number | null
  matrix_version_no: number | null
  routing_blocked_at?: string | null
  routing_block_code?: string | null
  routing_block_message?: string | null
  duplicate_warning_acknowledged_at: string | null
  procurement_engaged_at?: string | null
  procurement_engagement_ref?: string | null
  submitted_at: string | null
  cancelled_at: string | null
  cancelled_by: PurchaseRequestActorRef | null
  cancellation_reason: string | null
  lines?: PurchaseRequestLine[]
  latest_financial_review?: FinancialReviewSummary | null
  approval_instances?: PurchaseRequestApprovalInstance[]
  created_at?: string | null
  updated_at?: string | null
}

export interface PurchaseApprovalMatrix {
  id: number
  name: string
  status: 'draft' | 'published' | 'retired' | string
  version_no: number
  effective_from: string | null
  published_at: string | null
  rules?: Array<{
    id: number
    min_amount: string | null
    max_amount: string | null
    request_kind: string | null
    organization_unit_id: number | null
    season_id: number | null
    applies_when_unpriced: boolean
  }>
  levels?: Array<{ id: number; level_order: number; name: string }>
}

export interface PurchaseApproverAuthority {
  id: number
  user_id: number
  user: PurchaseRequestActorRef | null
  max_amount: string
  currency: string
  covers_unpriced: boolean
  organization_unit_id: number | null
  effective_from: string | null
  effective_to: string | null
  revoked_at: string | null
}

export interface PurchaseApprovalDelegation {
  id: number
  from_user_id: number
  from_user: PurchaseRequestActorRef | null
  to_user_id: number
  to_user: PurchaseRequestActorRef | null
  max_amount: string
  currency: string
  starts_at: string | null
  ends_at: string | null
  revoked_at: string | null
}

export interface PurchaseApprovalSlaPolicy {
  id: number
  priority: string
  level_order: number
  target_hours: number
  reminder_offset_hours: number | null
}

export interface PurchaseRequestVersion {
  id: number
  version_no: number
  status: string
  request_kind: string
  season_id: number | null
  title: string
  purpose: string
  priority: string
  estimated_total_priced: string
  has_unpriced_lines: boolean
  snapshot_at: string | null
  submitted_by: number
  lines?: Array<{
    line_no: number
    description: string
    quantity: string
    unit: string
    need_date: string | null
    unit_estimate: string | null
    line_estimate: string | null
    unpriced_reason: string | null
  }>
}

export interface PurchaseRequestListFilters {
  status?: PurchaseRequestStatus | ''
  request_kind?: PurchaseRequestKind | ''
  season_id?: number | null
  priority?: PurchaseRequestPriority | ''
  optional_supplier_id?: number | null
  beneficiary_organization_unit_id?: number | null
  requested_by?: number | null
  q?: string
  page?: number
  per_page?: number
}

export interface CreatePurchaseRequestPayload {
  request_kind: PurchaseRequestKind
  season_id?: number | null
  title: string
  purpose: string
  priority: PurchaseRequestPriority
  urgency_justification?: string | null
  beneficiary_organization_unit_id: number
  optional_supplier_id?: number | null
  currency?: string
  lines: Array<{
    line_no?: number
    description: string
    specifications?: string | null
    quantity: number
    unit: string
    need_date: string
    inventory_item_id?: number | null
    unit_estimate?: number | null
    line_estimate?: number | null
    unpriced_reason?: string | null
  }>
}

export interface UpdatePurchaseRequestPayload {
  request_kind?: PurchaseRequestKind
  season_id?: number | null
  title?: string
  purpose?: string
  priority?: PurchaseRequestPriority
  urgency_justification?: string | null
  beneficiary_organization_unit_id?: number
  optional_supplier_id?: number | null
  currency?: string
}

export interface DuplicateHint {
  purchase_request_id: number
  pr_number: string
  matched_line_nos: number[]
}
