import type {
  ListViewState,
  OperationalRisk,
  RiskClosureOutcome,
  RiskFormState,
  RiskSeverity,
  RiskStatus,
  RiskTreatmentFormState,
} from '../types/risks'

export function calculateRiskScore(likelihood: number, impact: number): number {
  return likelihood * impact
}

export function severityFromScore(score: number): RiskSeverity {
  if (score <= 4) return 'low'
  if (score <= 9) return 'medium'
  if (score <= 15) return 'high'
  return 'critical'
}

export function previewAssessment(
  likelihood: number | null | '',
  impact: number | null | '',
): { score: number | null; severity: RiskSeverity | null } {
  const l = typeof likelihood === 'number' ? likelihood : Number(likelihood)
  const i = typeof impact === 'number' ? impact : Number(impact)
  if (!Number.isInteger(l) || !Number.isInteger(i) || l < 1 || l > 5 || i < 1 || i > 5) {
    return { score: null, severity: null }
  }
  const score = calculateRiskScore(l, i)
  return { score, severity: severityFromScore(score) }
}

export function emptyRiskForm(): RiskFormState {
  return {
    title: '',
    description: '',
    season_pilgrim_id: '',
    group_id: '',
    facility_id: '',
    transport_season_vehicle_id: '',
    project_id: '',
  }
}

export function emptyTreatmentForm(): RiskTreatmentFormState {
  return {
    title: '',
    plan_body: '',
    owner_employee_id: '',
    due_at: '',
    status: 'planned',
  }
}

export function validateRiskForm(form: RiskFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  return errors
}

export function validateTreatmentForm(form: RiskTreatmentFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  if (!form.plan_body.trim()) errors.plan_body = 'required'
  if (form.owner_employee_id === '' || form.owner_employee_id == null) {
    errors.owner_employee_id = 'required'
  }
  return errors
}

export function riskStatusBadgeClass(status: RiskStatus): string {
  switch (status) {
    case 'identified':
      return 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-300/80'
    case 'under_assessment':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'approved':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'treating':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'closed':
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function riskSeverityBadgeClass(severity: RiskSeverity | null): string {
  switch (severity) {
    case 'low':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'medium':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'high':
      return 'bg-orange-100 text-orange-950 ring-1 ring-inset ring-orange-300/80'
    case 'critical':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    default:
      return 'bg-neutral-100 text-neutral-600 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function resolveRisksListState(options: {
  hasSeason: boolean
  isLoading: boolean
  isError: boolean
  count: number
}): ListViewState {
  if (!options.hasSeason) return 'need_season'
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  return options.count === 0 ? 'empty' : 'ready'
}

/** Client hint only — server enforces SoD and permission. */
export function canApproveRiskAssessment(options: {
  risk: Pick<OperationalRisk, 'status' | 'created_by'>
  currentUserId: number | null | undefined
  hasApprovePermission: boolean
}): boolean {
  if (!options.hasApprovePermission) return false
  if (options.risk.status !== 'under_assessment') return false
  const creatorId = options.risk.created_by?.id
  if (creatorId != null && options.currentUserId != null && creatorId === options.currentUserId) {
    return false
  }
  return true
}

export function canAssignRisk(status: RiskStatus): boolean {
  return status === 'approved'
}

export function canCloseRisk(status: RiskStatus): boolean {
  return status === 'treating'
}

export function canSoftDeleteRisk(status: RiskStatus): boolean {
  return status === 'identified'
}

export function canAssessRisk(status: RiskStatus): boolean {
  return status === 'identified' || status === 'under_assessment'
}

export function canManageTreatment(status: RiskStatus): boolean {
  return status === 'under_assessment' || status === 'approved' || status === 'treating'
}

export function mapRiskErrorCode(code: string | undefined): string {
  const known = [
    'RISK_SELF_APPROVAL_FORBIDDEN',
    'RISK_TREATMENT_REQUIRED',
    'RISK_ASSESSMENT_INCOMPLETE',
    'RISK_INVALID_TRANSITION',
    'RISK_IMMUTABLE',
    'CONFLICT_STALE_STATE',
    'VALIDATION_ERROR',
  ]
  return known.includes(code ?? '') ? code! : 'generic'
}

export function isValidClosureOutcome(value: string): value is RiskClosureOutcome {
  return ['mitigated', 'accepted', 'transferred', 'cancelled'].includes(value)
}
