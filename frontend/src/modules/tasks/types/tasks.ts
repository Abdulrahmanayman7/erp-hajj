export type TaskStatus = 'draft' | 'assigned' | 'in_progress' | 'completed' | 'cancelled'
export type TaskPriority = 'low' | 'medium' | 'high'

export interface TaskEmployeeSummary { id: number; employee_number: string; full_name: string }
export interface TaskOrgUnitSummary { id: number; name: string; code: string }
export interface TaskActorSummary { id: number; name: string }
export interface TaskDecisionSummary { id: number; decision_number: string; title: string; status: string }
export interface TaskTransition {
  id: number
  from_status: TaskStatus | null
  to_status: TaskStatus
  comment: string | null
  performed_by: TaskActorSummary | null
  correlation_id: string | null
  created_at: string | null
}
export interface TaskAssignmentHistoryEntry {
  id: number
  from_employee: TaskEmployeeSummary | null
  to_employee: TaskEmployeeSummary | null
  comment: string | null
  performed_by: TaskActorSummary | null
  correlation_id: string | null
  created_at: string | null
}
export interface Task {
  id: number; task_number: string; title: string; description: string | null; notes: string | null
  status: TaskStatus; priority: TaskPriority; progress_percent: number
  decision_id: number | null; organization_unit_id: number | null; assigned_to_employee_id: number | null
  start_date: string | null; due_date: string | null; completed_at: string | null; completion_notes: string | null
  is_overdue: boolean
  decision: TaskDecisionSummary | null; organization_unit: TaskOrgUnitSummary | null
  assigned_to_employee: TaskEmployeeSummary | null; created_by: TaskActorSummary | null
  status_transitions?: TaskTransition[]; assignment_history?: TaskAssignmentHistoryEntry[]
  created_at: string | null; updated_at: string | null
}
export interface TasksListMeta { current_page: number; per_page: number; total: number; last_page: number }
export interface ListTasksParams {
  search?: string; status?: TaskStatus | 'all' | ''; decision_id?: number | ''
  organization_unit_id?: number | ''; assigned_to_employee_id?: number | ''; assigned_to_me?: boolean | ''
  priority?: TaskPriority | 'all' | ''; due_date_from?: string; due_date_to?: string; overdue?: boolean | ''
  page?: number; per_page?: number; sort?: string
}
export interface TaskPayload {
  title?: string; description?: string | null; notes?: string | null; priority?: TaskPriority | null
  decision_id?: number | null; organization_unit_id?: number | null; assigned_to_employee_id?: number | null
  start_date?: string | null; due_date?: string | null
}
export interface TaskFormState {
  title: string; description: string; notes: string; priority: TaskPriority
  decision_id: number | ''; organization_unit_id: number | ''; assigned_to_employee_id: number | ''
  start_date: string; due_date: string
}
