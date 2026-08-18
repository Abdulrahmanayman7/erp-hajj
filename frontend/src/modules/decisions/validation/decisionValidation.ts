import type { DecisionFormState, DecisionLifecycleAction, DecisionStatus } from '../types/decisions'

export const DECISION_STATUSES: DecisionStatus[] = ['draft', 'pending_approval', 'approved', 'closed', 'cancelled']
export function validateDecisionForm(form: DecisionFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  if (!form.body.trim()) errors.body = 'required'
  if (form.effective_date && form.due_date && form.due_date < form.effective_date) errors.due_date = 'dateRange'
  return errors
}
export function decisionStatusBadgeClass(status: DecisionStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-300/80'
    case 'pending_approval':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'approved':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'closed':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'cancelled':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function decisionStatusDotClass(status: DecisionStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-500'
    case 'pending_approval':
      return 'bg-amber-500'
    case 'approved':
      return 'bg-emerald-500'
    case 'closed':
      return 'bg-sky-500'
    case 'cancelled':
      return 'bg-red-500'
    default:
      return 'bg-neutral-400'
  }
}
export function availableLifecycleActions(status: DecisionStatus): { action: DecisionLifecycleAction; permission: string; comment?: boolean }[] {
  if (status === 'draft') return [{ action: 'submit', permission: 'decisions.update' }, { action: 'cancel', permission: 'decisions.update' }]
  if (status === 'pending_approval') return [{ action: 'approve', permission: 'decisions.approve' }, { action: 'return-draft', permission: 'decisions.approve', comment: true }, { action: 'cancel', permission: 'decisions.update' }]
  return status === 'approved' ? [{ action: 'close', permission: 'decisions.close' }] : []
}
export function mapDecisionErrorCode(code: string | undefined): string {
  return ['DECISION_INVALID_STATUS_TRANSITION', 'DECISION_COMMENT_REQUIRED', 'DECISION_NOT_EDITABLE', 'DECISION_DELETE_FORBIDDEN', 'DECISION_ALREADY_CREATED_FROM_RECOMMENDATION', 'DECISION_CLOSE_NOT_ALLOWED'].includes(code ?? '') ? code! : 'generic'
}
export function resolveDecisionsListState(options: { isLoading: boolean; isError: boolean; count: number }): 'loading' | 'error' | 'empty' | 'ready' {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  return options.count === 0 ? 'empty' : 'ready'
}
export function canCreateDecisionFromRecommendation(options: { recommendationStatus: string; meetingStatus: string; hasLinkedDecision: boolean; permissions: string[] }): boolean {
  return options.recommendationStatus === 'final' && options.meetingStatus === 'completed' && !options.hasLinkedDecision && options.permissions.includes('decisions.create')
}
