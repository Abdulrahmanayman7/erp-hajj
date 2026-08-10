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
  return ({ draft: 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200/80', pending_approval: 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/70', approved: 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70', closed: 'bg-sky-50 text-sky-900 ring-1 ring-sky-200/70', cancelled: 'bg-red-50 text-red-800 ring-1 ring-red-200/70' } as const)[status]
}
export function availableLifecycleActions(status: DecisionStatus): { action: DecisionLifecycleAction; permission: string; comment?: boolean }[] {
  if (status === 'draft') return [{ action: 'submit', permission: 'decisions.update' }, { action: 'cancel', permission: 'decisions.update' }]
  if (status === 'pending_approval') return [{ action: 'approve', permission: 'decisions.approve' }, { action: 'return-draft', permission: 'decisions.approve', comment: true }, { action: 'cancel', permission: 'decisions.update' }]
  return status === 'approved' ? [{ action: 'close', permission: 'decisions.close' }] : []
}
export function mapDecisionErrorCode(code: string | undefined): string {
  return ['DECISION_INVALID_STATUS_TRANSITION', 'DECISION_COMMENT_REQUIRED', 'DECISION_NOT_EDITABLE', 'DECISION_DELETE_FORBIDDEN', 'DECISION_ALREADY_CREATED_FROM_RECOMMENDATION'].includes(code ?? '') ? code! : 'generic'
}
export function resolveDecisionsListState(options: { isLoading: boolean; isError: boolean; count: number }): 'loading' | 'error' | 'empty' | 'ready' {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  return options.count === 0 ? 'empty' : 'ready'
}
export function canCreateDecisionFromRecommendation(options: { recommendationStatus: string; meetingStatus: string; hasLinkedDecision: boolean; permissions: string[] }): boolean {
  return options.recommendationStatus === 'final' && options.meetingStatus === 'completed' && !options.hasLinkedDecision && options.permissions.includes('decisions.create')
}
