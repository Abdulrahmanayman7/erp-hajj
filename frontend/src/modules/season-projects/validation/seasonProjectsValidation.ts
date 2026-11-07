import type {
  SeasonProjectFormState,
  SeasonProjectPhaseFormState,
  SeasonProjectStatus,
} from '../types/seasonProjects'

export type ListViewState = 'need_season' | 'loading' | 'error' | 'empty' | 'ready'

export function resolveListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
  hasSeason?: boolean
}): ListViewState {
  if (options.hasSeason === false) return 'need_season'
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function emptyProjectForm(): SeasonProjectFormState {
  return {
    title: '',
    description: '',
    owner_employee_id: '',
  }
}

export function emptyPhaseForm(): SeasonProjectPhaseFormState {
  return {
    title: '',
    sort_order: '',
    starts_on: '',
    ends_on: '',
    status: 'planned',
  }
}

export function validateProjectForm(form: SeasonProjectFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  return errors
}

export function validatePhaseForm(form: SeasonProjectPhaseFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.title.trim()) errors.title = 'required'
  if (
    form.starts_on &&
    form.ends_on &&
    form.starts_on > form.ends_on
  ) {
    errors.ends_on = 'range'
  }
  return errors
}

export function formatMoneyDisplay(
  value: string | number | null | undefined,
  available = true,
): string {
  if (!available) return '—'
  if (value == null || value === '') return '—'
  return String(value)
}

export function mapSeasonProjectErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SEASON_PROJECT_INVALID_TRANSITION':
      return 'invalidTransition'
    case 'SEASON_PROJECT_OWNER_REQUIRED':
      return 'ownerRequired'
    case 'SEASON_PROJECT_OWNER_INVALID':
      return 'ownerInvalid'
    case 'SEASON_PROJECT_TASK_FROZEN':
      return 'taskFrozen'
    case 'SEASON_PROJECT_TASK_PROJECT_INVALID':
      return 'taskProjectInvalid'
    case 'SEASON_PROJECT_TASK_PHASE_INVALID':
      return 'taskPhaseInvalid'
    case 'SEASON_PROJECT_TASK_CREATE_NOT_ALLOWED':
      return 'taskCreateNotAllowed'
    case 'SEASON_PROJECT_CANCEL_HAS_OPEN_TASKS':
      return 'cancelHasOpenTasks'
    case 'SEASON_PROJECT_PHASE_SORT_CONFLICT':
      return 'phaseSortConflict'
    case 'SEASON_PROJECT_PO_NOT_LINKABLE':
      return 'poNotLinkable'
    case 'SEASON_PROJECT_PO_ALREADY_LINKED':
      return 'poAlreadyLinked'
    case 'SEASON_PROJECT_PO_CURRENCY_MISMATCH':
      return 'poCurrencyMismatch'
    case 'SEASON_PROJECT_BUDGET_UPDATE_NOT_ALLOWED':
      return 'budgetUpdateNotAllowed'
    case 'SEASON_PROJECT_BUDGET_CURRENCY_LOCKED':
      return 'budgetCurrencyLocked'
    case 'SEASON_PROJECT_PO_UNLINK_NOT_ALLOWED':
      return 'poUnlinkNotAllowed'
    case 'SEASON_PROJECT_PO_UNLINK_HAS_POSTED_RECEIPTS':
      return 'poUnlinkHasPostedReceipts'
    case 'SEASON_PROJECT_DELETE_NOT_ALLOWED':
      return 'deleteNotAllowed'
    case 'SEASON_PROJECT_DELETE_HAS_CHILDREN':
      return 'deleteHasChildren'
    case 'SEASON_CLOSED':
      return 'seasonClosed'
    default:
      return 'generic'
  }
}

export function canEditProjectFields(status: SeasonProjectStatus): boolean {
  return status === 'draft' || status === 'active'
}

export function canSoftDeleteProject(status: SeasonProjectStatus): boolean {
  return status === 'draft'
}

export function canActivateProject(status: SeasonProjectStatus): boolean {
  return status === 'draft'
}

export function canCompleteProject(status: SeasonProjectStatus): boolean {
  return status === 'active'
}

export function canCancelProject(status: SeasonProjectStatus): boolean {
  return status === 'draft' || status === 'active'
}

export function canReassignOwner(status: SeasonProjectStatus): boolean {
  return status === 'active'
}

export function canManageBudgetLifecycle(status: SeasonProjectStatus): boolean {
  return status === 'draft' || status === 'active'
}

export function canLinkPoLifecycle(status: SeasonProjectStatus): boolean {
  return status === 'draft' || status === 'active'
}

export function isProjectTerminal(status: SeasonProjectStatus): boolean {
  return status === 'completed' || status === 'cancelled'
}

export function isTaskMutationFrozenForCompletedProject(
  projectStatus: SeasonProjectStatus,
  action: 'create' | 'update' | 'assign' | 'start' | 'progress' | 'delete' | 'complete' | 'cancel',
): boolean {
  if (projectStatus !== 'completed') return false
  return action !== 'complete' && action !== 'cancel'
}
