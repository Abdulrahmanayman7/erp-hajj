export type SeasonProjectStatus = 'draft' | 'active' | 'completed' | 'cancelled'
export type SeasonProjectPhaseStatus = 'planned' | 'active' | 'done'
export type TaskDelayState = 'none' | 'delayed'

export interface SeasonProjectOwner {
  id: number
  employee_number: string
  full_name: string
}

export interface SeasonProject {
  id: number
  number: string
  title: string
  description: string | null
  status: SeasonProjectStatus
  season_id: number
  owner_employee_id: number | null
  owner_employee: SeasonProjectOwner | null
  budget_amount: string | null
  currency_code: string
  progress_percent: string | number
  delay_summary: string | null
  activated_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface SeasonProjectPhase {
  id: number
  project_id: number
  season_id: number
  title: string
  sort_order: number
  starts_on: string | null
  ends_on: string | null
  status: SeasonProjectPhaseStatus
  created_at: string | null
  updated_at: string | null
}

export interface SeasonProjectPoLink {
  id: number
  purchase_order_id: number
  po_number: string
  po_status: string
  po_season_id: number | null
  currency_code: string
  ordered_amount: string
  outstanding_commitment: string
  actual_spending: string
  linked_at: string | null
  notes: string | null
}

export interface SeasonProjectBudgetFinancials {
  available: boolean
  unavailable_reason: string | null
  outstanding_commitment: string | null
  actual_spending: string | null
  remaining: string | null
  valuation_basis: string
}

export interface SeasonProjectBudget {
  project_id: number
  season_id: number
  status: SeasonProjectStatus
  budget_amount: string | null
  currency_code: string
  financials: SeasonProjectBudgetFinancials
  po_links: SeasonProjectPoLink[]
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSeasonProjectsParams {
  status?: SeasonProjectStatus | ''
  owner_employee_id?: number | ''
  q?: string
  page?: number
  per_page?: number
  seasonId?: number | null
}

export interface SeasonProjectFormState {
  title: string
  description: string
  owner_employee_id: number | ''
}

export interface SeasonProjectPhaseFormState {
  title: string
  sort_order: number | ''
  starts_on: string
  ends_on: string
  status: SeasonProjectPhaseStatus
}

export const SEASON_PROJECT_STATUSES: SeasonProjectStatus[] = [
  'draft',
  'active',
  'completed',
  'cancelled',
]

export const SEASON_PROJECT_PHASE_STATUSES: SeasonProjectPhaseStatus[] = [
  'planned',
  'active',
  'done',
]

export type SeasonProjectProgressBaselineStatus = 'draft' | 'approved'

export interface SeasonProjectProgressCheckpoint {
  id?: number
  effective_at: string
  planned_progress_percent: number
}

export interface SeasonProjectProgressBaseline {
  id: number
  version: number
  status: SeasonProjectProgressBaselineStatus
  approved_at: string | null
  approved_by: number | null
  checkpoints: SeasonProjectProgressCheckpoint[]
}

export interface SeasonProjectProgressCheckpointFormRow {
  effective_at: string
  planned_progress_percent: string
}

export interface SeasonProjectProgressLagMetric {
  state: 'available' | 'unavailable'
  unavailable_reason: 'no_approved_baseline' | 'no_checkpoint_at_evaluation_time' | null
  planned_progress_percent: number | null
  actual_progress_percent: number | null
  lag_percentage_points: number | null
  baseline_version: number | null
}
