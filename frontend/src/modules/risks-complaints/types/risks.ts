export type RiskStatus =
  | 'identified'
  | 'under_assessment'
  | 'approved'
  | 'treating'
  | 'closed'

export type RiskSeverity = 'low' | 'medium' | 'high' | 'critical'

export type RiskClosureOutcome = 'mitigated' | 'accepted' | 'transferred' | 'cancelled'

export type TreatmentStatus = 'planned' | 'in_progress' | 'done' | 'cancelled'

export type ListViewState = 'loading' | 'error' | 'empty' | 'ready' | 'need_season'

export interface RiskActorSummary {
  id: number
  name: string
  email: string
}

export interface RiskEmployeeSummary {
  id: number
  full_name: string
  employee_number: string | null
}

export interface RiskTreatment {
  id: number
  title: string
  plan_body: string
  owner_employee_id: number
  due_at: string | null
  status: TreatmentStatus
  owner_employee: RiskEmployeeSummary | null
}

export interface OperationalRisk {
  id: number
  season_id: number
  risk_number: string
  title: string
  description: string | null
  status: RiskStatus
  likelihood: number | null
  impact: number | null
  score: number | null
  severity_level: RiskSeverity | null
  closure_outcome: RiskClosureOutcome | null
  close_reason: string | null
  owner_employee_id: number | null
  season_pilgrim_id: number | null
  group_id: number | null
  facility_id: number | null
  transport_season_vehicle_id: number | null
  project_id: number | null
  approved_at: string | null
  created_at: string | null
  updated_at: string | null
  created_by: RiskActorSummary | null
  approved_by: RiskActorSummary | null
  owner_employee: RiskEmployeeSummary | null
  treatment: RiskTreatment | null
}

export interface RisksListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListRisksParams {
  q?: string
  status?: RiskStatus | ''
  severity_level?: RiskSeverity | ''
  owner_employee_id?: number | ''
  page?: number
  per_page?: number
  seasonId?: number | null
}

export interface RiskCreatePayload {
  title: string
  description?: string | null
  likelihood?: number | null
  impact?: number | null
  season_pilgrim_id?: number | null
  group_id?: number | null
  facility_id?: number | null
  transport_season_vehicle_id?: number | null
  project_id?: number | null
}

export interface RiskUpdatePayload {
  title?: string
  description?: string | null
}

export interface RiskAssessPayload {
  likelihood: number
  impact: number
}

export interface RiskTreatmentPayload {
  title: string
  plan_body: string
  owner_employee_id: number
  due_at?: string | null
  status?: TreatmentStatus
}

export interface RiskAssignPayload {
  owner_employee_id: number
}

export interface RiskClosePayload {
  closure_outcome: RiskClosureOutcome
  close_reason?: string | null
}

export interface RiskFormState {
  title: string
  description: string
  season_pilgrim_id: number | ''
  group_id: number | ''
  facility_id: number | ''
  transport_season_vehicle_id: number | ''
  project_id: number | ''
}

export interface RiskTreatmentFormState {
  title: string
  plan_body: string
  owner_employee_id: number | ''
  due_at: string
  status: TreatmentStatus
}

export const RISK_STATUSES: RiskStatus[] = [
  'identified',
  'under_assessment',
  'approved',
  'treating',
  'closed',
]

export const RISK_SEVERITIES: RiskSeverity[] = ['low', 'medium', 'high', 'critical']

export const RISK_CLOSURE_OUTCOMES: RiskClosureOutcome[] = [
  'mitigated',
  'accepted',
  'transferred',
  'cancelled',
]

export const TREATMENT_STATUSES: TreatmentStatus[] = [
  'planned',
  'in_progress',
  'done',
  'cancelled',
]
