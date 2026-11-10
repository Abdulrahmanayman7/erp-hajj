import type {
  SeasonProjectFormState,
  SeasonProjectPhaseFormState,
  SeasonProjectProgressBaseline,
  SeasonProjectProgressCheckpointFormRow,
  SeasonProjectProgressLagMetric,
  SeasonProjectStatus,
} from '../types/seasonProjects'

/** Matches backend UpsertSeasonProjectProgressBaselineDraftRequest::MAX_CHECKPOINTS */
export const MAX_BASELINE_CHECKPOINTS = 100

/** Owner-approved U02 open/clear thresholds (percentage points). */
export const PROGRESS_LAG_OPEN_THRESHOLD = 10
export const PROGRESS_LAG_CLEAR_THRESHOLD = 8

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

export function emptyCheckpointFormRow(now = new Date()): SeasonProjectProgressCheckpointFormRow {
  const local = new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
  return {
    effective_at: local.toISOString().slice(0, 16),
    planned_progress_percent: '0',
  }
}

export function checkpointFormToIso(localDatetime: string): string {
  const trimmed = localDatetime.trim()
  if (!trimmed) return ''
  const parsed = new Date(trimmed)
  if (Number.isNaN(parsed.getTime())) return trimmed
  return parsed.toISOString()
}

export function isoToDatetimeLocalValue(iso: string): string {
  const parsed = new Date(iso)
  if (Number.isNaN(parsed.getTime())) return ''
  const local = new Date(parsed.getTime() - parsed.getTimezoneOffset() * 60_000)
  return local.toISOString().slice(0, 16)
}

/**
 * Client-side checkpoint validation mirroring the backend FormRequest
 * (bounds, uniqueness, non-decreasing planned %). Server remains authoritative.
 */
export function validateBaselineCheckpointForm(
  rows: SeasonProjectProgressCheckpointFormRow[],
): Record<string, string> {
  const errors: Record<string, string> = {}
  if (rows.length < 1) {
    errors.checkpoints = 'checkpointsRequired'
    return errors
  }
  if (rows.length > MAX_BASELINE_CHECKPOINTS) {
    errors.checkpoints = 'checkpointsTooMany'
    return errors
  }

  const normalized: Array<{ at: string; pct: number; index: number }> = []
  rows.forEach((row, index) => {
    const at = row.effective_at.trim()
    if (!at) {
      errors[`checkpoints.${index}.effective_at`] = 'required'
      return
    }
    const pct = Number(row.planned_progress_percent)
    if (!Number.isFinite(pct)) {
      errors[`checkpoints.${index}.planned_progress_percent`] = 'required'
      return
    }
    if (pct < 0 || pct > 100) {
      errors[`checkpoints.${index}.planned_progress_percent`] = 'percentRange'
      return
    }
    normalized.push({ at: checkpointFormToIso(at), pct, index })
  })

  if (Object.keys(errors).length) return errors

  const seen = new Set<string>()
  for (const row of normalized) {
    if (seen.has(row.at)) {
      errors.checkpoints = 'checkpointsDuplicateEffectiveAt'
      return errors
    }
    seen.add(row.at)
  }

  const ordered = [...normalized].sort((a, b) => a.at.localeCompare(b.at))
  let previous: number | null = null
  for (const row of ordered) {
    if (previous !== null && row.pct + 0.00001 < previous) {
      errors.checkpoints = 'checkpointsNotNonDecreasing'
      return errors
    }
    previous = row.pct
  }

  return errors
}

export function computeProgressLagMetric(
  baselines: SeasonProjectProgressBaseline[],
  actualProgressPercent: number | string,
  now: Date = new Date(),
): SeasonProjectProgressLagMetric {
  const actual = Number(actualProgressPercent)
  const approved = [...baselines]
    .filter((b) => b.status === 'approved')
    .sort((a, b) => b.version - a.version)[0]

  if (!approved) {
    return {
      state: 'unavailable',
      unavailable_reason: 'no_approved_baseline',
      planned_progress_percent: null,
      actual_progress_percent: Number.isFinite(actual) ? actual : null,
      lag_percentage_points: null,
      baseline_version: null,
    }
  }

  const nowMs = now.getTime()
  const checkpoint = [...approved.checkpoints]
    .filter((c) => {
      const t = new Date(c.effective_at).getTime()
      return Number.isFinite(t) && t <= nowMs
    })
    .sort((a, b) => new Date(b.effective_at).getTime() - new Date(a.effective_at).getTime())[0]

  if (!checkpoint) {
    return {
      state: 'unavailable',
      unavailable_reason: 'no_checkpoint_at_evaluation_time',
      planned_progress_percent: null,
      actual_progress_percent: Number.isFinite(actual) ? actual : null,
      lag_percentage_points: null,
      baseline_version: approved.version,
    }
  }

  const planned = Number(checkpoint.planned_progress_percent)
  const lag = planned - (Number.isFinite(actual) ? actual : 0)

  return {
    state: 'available',
    unavailable_reason: null,
    planned_progress_percent: planned,
    actual_progress_percent: Number.isFinite(actual) ? actual : null,
    lag_percentage_points: lag,
    baseline_version: approved.version,
  }
}
