export type DecisionStatus = 'draft' | 'pending_approval' | 'approved' | 'closed' | 'cancelled'

export interface DecisionEmployeeSummary { id: number; employee_number: string; full_name: string }
export interface DecisionOrgUnitSummary { id: number; name: string; code: string }
export interface DecisionActorSummary { id: number; name: string }
export interface DecisionSourceRecommendation { id: number; title: string; status: string; meeting_id: number }
export interface DecisionSourceMeeting { id: number; meeting_number: string; title: string; status: string }
export interface DecisionTransition {
  id: number
  from_status: DecisionStatus | null
  to_status: DecisionStatus
  comment: string | null
  performed_by: DecisionActorSummary | null
  correlation_id: string | null
  created_at: string | null
}
export interface DecisionTasksSummary { total: number; open: number; completed: number; cancelled: number }
export interface Decision {
  id: number; decision_number: string; title: string; body: string; notes: string | null
  status: DecisionStatus; source_recommendation_id: number | null
  organization_unit_id: number | null; issued_by_employee_id: number | null; responsible_employee_id: number | null
  effective_date: string | null; due_date: string | null
  organization_unit: DecisionOrgUnitSummary | null; issued_by_employee: DecisionEmployeeSummary | null
  responsible_employee: DecisionEmployeeSummary | null; created_by: DecisionActorSummary | null
  source_recommendation: DecisionSourceRecommendation | null; source_meeting: DecisionSourceMeeting | null
  status_transitions?: DecisionTransition[]; tasks_summary?: DecisionTasksSummary
  created_at: string | null; updated_at: string | null
}
export interface DecisionsListMeta { current_page: number; per_page: number; total: number; last_page: number }
export interface ListDecisionsParams {
  search?: string; status?: DecisionStatus | 'all' | ''; organization_unit_id?: number | ''
  responsible_employee_id?: number | ''; has_source_recommendation?: boolean | ''; meeting_id?: number | ''
  effective_date_from?: string; effective_date_to?: string; due_date_from?: string; due_date_to?: string
  page?: number; per_page?: number; sort?: string; direction?: string
}
export interface DecisionPayload {
  title?: string | null; body?: string | null; notes?: string | null; source_recommendation_id?: number | null
  organization_unit_id?: number | null; issued_by_employee_id?: number | null; responsible_employee_id?: number | null
  effective_date?: string | null; due_date?: string | null
}
export interface DecisionFormState {
  title: string; body: string; notes: string; organization_unit_id: number | ''
  issued_by_employee_id: number | ''; responsible_employee_id: number | ''; effective_date: string; due_date: string
}
export type DecisionLifecycleAction = 'submit' | 'return-draft' | 'approve' | 'cancel' | 'close'
