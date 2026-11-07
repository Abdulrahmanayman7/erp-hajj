import type {
  ComplaintFormState,
  ComplaintStatus,
  ListViewState,
} from '../types/complaints'

export function emptyComplaintForm(): ComplaintFormState {
  return {
    title: '',
    body: '',
    category_code: '',
    season_pilgrim_id: '',
    group_id: '',
    facility_id: '',
    transport_season_vehicle_id: '',
    project_id: '',
  }
}

export function validateComplaintForm(form: ComplaintFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  if (!form.body.trim()) errors.body = 'required'
  if (!form.category_code) errors.category_code = 'required'
  return errors
}

export function complaintStatusBadgeClass(status: ComplaintStatus): string {
  switch (status) {
    case 'new':
      return 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-300/80'
    case 'under_review':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'in_progress':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'resolved':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'closed':
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function resolveComplaintsListState(options: {
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

export function canStartComplaintReview(status: ComplaintStatus): boolean {
  return status === 'new'
}

export function canAssignComplaint(status: ComplaintStatus): boolean {
  return status === 'under_review'
}

export function canEscalateComplaint(status: ComplaintStatus): boolean {
  return status === 'under_review' || status === 'in_progress' || status === 'resolved'
}

export function canResolveComplaint(status: ComplaintStatus): boolean {
  return status === 'in_progress'
}

export function canCloseComplaint(status: ComplaintStatus): boolean {
  return status === 'resolved'
}

export function canReopenComplaint(status: ComplaintStatus): boolean {
  return status === 'closed'
}

export function canSoftDeleteComplaint(status: ComplaintStatus): boolean {
  return status === 'new'
}

export function canAddComplaintNote(status: ComplaintStatus): boolean {
  return status !== 'closed'
}

export function mapComplaintErrorCode(code: string | undefined): string {
  const known = [
    'COMPLAINT_INVALID_TRANSITION',
    'COMPLAINT_IMMUTABLE',
    'COMPLAINT_REOPEN_REASON_REQUIRED',
    'COMPLAINT_CLOSE_REASON_REQUIRED',
    'COMPLAINT_EMPLOYEE_INVALID',
    'CONFLICT_STALE_STATE',
    'VALIDATION_ERROR',
  ]
  return known.includes(code ?? '') ? code! : 'generic'
}
