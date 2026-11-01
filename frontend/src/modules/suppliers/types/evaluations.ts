export type EvaluationStatus = 'draft' | 'pending_approval' | 'approved' | 'rejected'
export type EvaluationKind = 'qualification' | 'periodic' | 'operational' | 'post_season'
export type AppealStatus = 'open' | 'accepted' | 'rejected'

export interface EvaluationUserRef {
  id: number
  name: string
}

export interface EvaluationScoreSnapshot {
  id: number
  source_criterion_id: number
  criterion_name: string
  weight: string
  score: string
  note: string | null
  sort_order: number
}

export interface EvaluationDraftScore {
  criterion_id: number
  score: number | string
  note?: string | null
}

export interface SupplierEvaluation {
  id: number
  evaluation_number: string
  supplier_id: number
  supplier_category_id: number
  evaluation_kind: EvaluationKind
  season_id: number | null
  status: EvaluationStatus
  criteria_pack_id: number
  criteria_pack_version_no: number
  total_score: string | null
  monitoring_recommended: boolean
  prepared_by: EvaluationUserRef | null
  submitted_at: string | null
  approved_by: EvaluationUserRef | null
  approved_at: string | null
  rejected_by: EvaluationUserRef | null
  rejected_at: string | null
  rejection_reason: string | null
  version_no: number
  evaluation_family_key: string
  supersedes_evaluation_id: number | null
  origin_appeal_id: number | null
  notes: string | null
  draft_scores: EvaluationDraftScore[] | null
  score_snapshots?: EvaluationScoreSnapshot[]
  supplier?: {
    id: number
    supplier_number: string
    display_name: string
  }
  created_at: string | null
  updated_at: string | null
}

export interface SupplierEvaluationAppeal {
  id: number
  evaluation_id: number
  reason: string
  status: AppealStatus
  filed_by: EvaluationUserRef | null
  filed_at: string | null
  reviewed_by: EvaluationUserRef | null
  reviewed_at: string | null
  review_outcome_note: string | null
  resulting_evaluation_id: number | null
  resulting_evaluation?: SupplierEvaluation
}

export interface EvaluationListFilters {
  supplier_id?: number | null
  supplier_category_id?: number | null
  evaluation_kind?: EvaluationKind | ''
  status?: EvaluationStatus | ''
  season_id?: number | null
  monitoring_recommended?: boolean | ''
  q?: string
  page?: number
  per_page?: number
}

export interface CreateEvaluationPayload {
  supplier_id: number
  supplier_category_id: number
  evaluation_kind: EvaluationKind
  season_id?: number | null
  monitoring_recommended?: boolean
  notes?: string | null
  scores?: EvaluationDraftScore[]
}

export interface UpdateEvaluationPayload {
  evaluation_kind?: EvaluationKind
  season_id?: number | null
  monitoring_recommended?: boolean
  notes?: string | null
  scores?: EvaluationDraftScore[]
}

export interface CriteriaPackSummary {
  id: number
  supplier_category_id: number
  version_no: number
  status: string
  criteria?: Array<{
    id: number
    name: string
    weight: string
    sort_order: number
    is_active: boolean
  }>
}
