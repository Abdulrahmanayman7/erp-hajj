export type ComplaintStatus =
  | 'new'
  | 'under_review'
  | 'in_progress'
  | 'resolved'
  | 'closed'

export type ComplaintCategory =
  | 'service'
  | 'accommodation'
  | 'transport'
  | 'procurement'
  | 'staffing'
  | 'safety'
  | 'other'

export type ListViewState = 'loading' | 'error' | 'empty' | 'ready' | 'need_season'

export interface ComplaintActorSummary {
  id: number
  name: string
  email: string
}

export interface ComplaintEmployeeSummary {
  id: number
  full_name: string
  employee_number: string | null
}

export interface ComplaintNote {
  id: number
  body: string
  created_by: ComplaintActorSummary | null
  created_at: string | null
}

export interface OperationalComplaint {
  id: number
  season_id: number
  complaint_number: string
  title: string
  body: string
  category_code: ComplaintCategory | string
  status: ComplaintStatus
  assignee_employee_id: number | null
  escalation_level: number
  escalated_to_employee_id: number | null
  escalate_reason: string | null
  escalated_at: string | null
  resolution_text: string | null
  resolved_at: string | null
  resolved_by: number | null
  closed_at: string | null
  closed_by: number | null
  close_reason: string | null
  reopened_at: string | null
  reopened_by: number | null
  reopen_reason: string | null
  season_pilgrim_id: number | null
  group_id: number | null
  facility_id: number | null
  transport_season_vehicle_id: number | null
  project_id: number | null
  created_at: string | null
  updated_at: string | null
  created_by: ComplaintActorSummary | null
  assignee_employee: ComplaintEmployeeSummary | null
  escalated_to_employee: ComplaintEmployeeSummary | null
  notes?: ComplaintNote[]
}

export interface ComplaintsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListComplaintsParams {
  q?: string
  status?: ComplaintStatus | ''
  category_code?: ComplaintCategory | ''
  assignee_employee_id?: number | ''
  page?: number
  per_page?: number
  seasonId?: number | null
}

export interface ComplaintCreatePayload {
  title: string
  body: string
  category_code: ComplaintCategory | string
  season_pilgrim_id?: number | null
  group_id?: number | null
  facility_id?: number | null
  transport_season_vehicle_id?: number | null
  project_id?: number | null
}

export interface ComplaintUpdatePayload {
  title?: string
  body?: string
  category_code?: string
  start_review?: boolean
}

export interface ComplaintAssignPayload {
  assignee_employee_id: number
}

export interface ComplaintEscalatePayload {
  escalated_to_employee_id: number
  escalate_reason: string
}

export interface ComplaintResolvePayload {
  resolution_text: string
}

export interface ComplaintClosePayload {
  close_reason: string
}

export interface ComplaintReopenPayload {
  reopen_reason: string
}

export interface ComplaintNotePayload {
  body: string
}

export interface ComplaintFormState {
  title: string
  body: string
  category_code: ComplaintCategory | ''
  season_pilgrim_id: number | ''
  group_id: number | ''
  facility_id: number | ''
  transport_season_vehicle_id: number | ''
  project_id: number | ''
}

export const COMPLAINT_STATUSES: ComplaintStatus[] = [
  'new',
  'under_review',
  'in_progress',
  'resolved',
  'closed',
]

export const COMPLAINT_CATEGORIES: ComplaintCategory[] = [
  'service',
  'accommodation',
  'transport',
  'procurement',
  'staffing',
  'safety',
  'other',
]
