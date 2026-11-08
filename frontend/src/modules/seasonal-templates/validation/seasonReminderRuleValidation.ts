import {
  APPROVED_RECIPIENT_PERMISSIONS,
  MAX_OFFSET_DAYS,
  REMINDER_SEVERITIES,
  REMINDER_TRIGGER_MODES,
  type CreateSeasonReminderRulePayload,
  type ReminderRuleFormState,
  type ReminderRuleStatus,
  type ReminderTriggerMode,
  type ReminderViewState,
  type SeasonReminderRule,
  type UpdateSeasonReminderRulePayload,
} from '../types/seasonReminderRules'

type Can = (permission: string) => boolean

/* ---------------------------------------------------------------- form state */

export function emptyReminderRuleForm(): ReminderRuleFormState {
  return {
    code: '',
    title: '',
    body: '',
    severity: 'info',
    trigger_mode: 'offset_from_season_start',
    trigger_on: '',
    offset_days: '',
    recipient_permission: '',
  }
}

export function ruleToForm(rule: SeasonReminderRule): ReminderRuleFormState {
  return {
    code: rule.code,
    title: rule.title,
    body: rule.body ?? '',
    severity: rule.severity,
    trigger_mode: rule.trigger_mode,
    trigger_on: rule.trigger_on ?? '',
    offset_days: rule.offset_days == null ? '' : String(rule.offset_days),
    recipient_permission: rule.recipient_permission,
  }
}

/* ---------------------------------------------------------------- recipient allowlist (UX mirror) */

export function isApprovedRecipientPermission(permission: string): boolean {
  return (APPROVED_RECIPIENT_PERMISSIONS as readonly string[]).includes(permission)
}

/** Whether a stored permission is no longer on the allowlist (activation would be refused by the server). */
export function hasUnapprovedRecipientPermission(rule: Pick<SeasonReminderRule, 'recipient_permission'>): boolean {
  return !isApprovedRecipientPermission(rule.recipient_permission)
}

/* ---------------------------------------------------------------- dates */

const DATE_RE = /^\d{4}-\d{2}-\d{2}$/

export function isValidDateOnly(value: string): boolean {
  if (!DATE_RE.test(value)) return false
  const [y, m, d] = value.split('-').map(Number)
  const parsed = new Date(Date.UTC(y, m - 1, d))
  return parsed.getUTCFullYear() === y && parsed.getUTCMonth() === m - 1 && parsed.getUTCDate() === d
}

function parseInteger(raw: string): number | null {
  const value = raw.trim()
  if (!/^[+-]?\d+$/.test(value)) return null
  return Number(value)
}

/** Adds whole days to a Y-m-d date (UTC arithmetic, no timezone drift). */
export function addDaysToDate(date: string, days: number): string | null {
  if (!isValidDateOnly(date)) return null
  const [y, m, d] = date.split('-').map(Number)
  const result = new Date(Date.UTC(y, m - 1, d + days))
  const mm = String(result.getUTCMonth() + 1).padStart(2, '0')
  const dd = String(result.getUTCDate()).padStart(2, '0')
  return `${String(result.getUTCFullYear()).padStart(4, '0')}-${mm}-${dd}`
}

/**
 * Display-only fire date (Y-m-d). Absolute rules fire on `trigger_on`; offset rules on
 * season start + offset. Returns null when it cannot be computed (the server computes the real one).
 */
export function computeFireDate(
  trigger: { trigger_mode: ReminderTriggerMode; trigger_on: string | null; offset_days: number | null },
  seasonStartsOn: string | null | undefined,
): string | null {
  if (trigger.trigger_mode === 'absolute_date') {
    return trigger.trigger_on && isValidDateOnly(trigger.trigger_on) ? trigger.trigger_on : null
  }
  if (trigger.offset_days == null || !Number.isInteger(trigger.offset_days) || !seasonStartsOn) return null
  return addDaysToDate(seasonStartsOn.slice(0, 10), trigger.offset_days)
}

/** Same as {@link computeFireDate} but for the (text based) form state. */
export function computeFormFireDate(form: ReminderRuleFormState, seasonStartsOn: string | null | undefined): string | null {
  const offset = parseInteger(form.offset_days)
  return computeFireDate(
    {
      trigger_mode: form.trigger_mode,
      trigger_on: form.trigger_on.trim() || null,
      offset_days: offset,
    },
    seasonStartsOn,
  )
}

/* ---------------------------------------------------------------- validation */

export function validateReminderRuleForm(
  form: ReminderRuleFormState,
  options: { editing: boolean },
): Record<string, string> {
  const errors: Record<string, string> = {}

  if (!options.editing) {
    const code = form.code.trim()
    if (!code) errors.code = 'required'
    else if (code.length > 64) errors.code = 'too_long'
  }

  const title = form.title.trim()
  if (!title) errors.title = 'required'
  else if (title.length > 255) errors.title = 'too_long'

  if (form.body.trim().length > 1000) errors.body = 'too_long'

  if (!(REMINDER_SEVERITIES as readonly string[]).includes(form.severity)) errors.severity = 'invalid_choice'

  if (!(REMINDER_TRIGGER_MODES as readonly string[]).includes(form.trigger_mode)) {
    errors.trigger_mode = 'invalid_choice'
  } else if (form.trigger_mode === 'absolute_date') {
    const triggerOn = form.trigger_on.trim()
    if (!triggerOn) errors.trigger_on = 'required'
    else if (!isValidDateOnly(triggerOn)) errors.trigger_on = 'invalid_date'
  } else {
    const raw = form.offset_days.trim()
    if (!raw) errors.offset_days = 'required'
    else {
      const offset = parseInteger(raw)
      if (offset === null) errors.offset_days = 'offset_not_integer'
      else if (Math.abs(offset) > MAX_OFFSET_DAYS) errors.offset_days = 'offset_out_of_range'
    }
  }

  const permission = form.recipient_permission.trim()
  if (!permission) errors.recipient_permission = 'required'
  else if (!isApprovedRecipientPermission(permission)) errors.recipient_permission = 'permission_not_allowed'

  return errors
}

/* ---------------------------------------------------------------- payload builders */

/** Fields of the inactive trigger mode are sent as null so the server never sees a mixed trigger. */
function triggerFields(form: ReminderRuleFormState): {
  trigger_mode: ReminderTriggerMode
  trigger_on: string | null
  offset_days: number | null
} {
  if (form.trigger_mode === 'absolute_date') {
    return { trigger_mode: 'absolute_date', trigger_on: form.trigger_on.trim(), offset_days: null }
  }
  return { trigger_mode: 'offset_from_season_start', trigger_on: null, offset_days: Number(form.offset_days.trim()) }
}

/** `status` is never part of the payload; the server always creates a draft. */
export function buildCreatePayload(form: ReminderRuleFormState): CreateSeasonReminderRulePayload {
  return {
    code: form.code.trim(),
    title: form.title.trim(),
    body: form.body.trim() || null,
    severity: form.severity,
    ...triggerFields(form),
    recipient_permission: form.recipient_permission.trim(),
  }
}

/** `code` is immutable and `status` is never client-set, so neither is sent. */
export function buildUpdatePayload(form: ReminderRuleFormState): UpdateSeasonReminderRulePayload {
  return {
    title: form.title.trim(),
    body: form.body.trim() || null,
    severity: form.severity,
    ...triggerFields(form),
    recipient_permission: form.recipient_permission.trim(),
  }
}

/* ---------------------------------------------------------------- permissions & actions (UX only) */

export function canViewReminderRules(can: Can): boolean {
  return can('season_reminders.view')
}

export function canManageReminderRules(can: Can): boolean {
  return can('season_reminders.manage')
}

/** Server: only draft/paused rules are editable and closed seasons are read-only. */
export function canEditReminderRule(status: ReminderRuleStatus, seasonClosed: boolean, can: Can): boolean {
  return can('season_reminders.manage') && !seasonClosed && (status === 'draft' || status === 'paused')
}

/** Activate (draft) and resume (paused) share the same endpoint; refused for closed seasons. */
export function canActivateReminderRule(status: ReminderRuleStatus, seasonClosed: boolean, can: Can): boolean {
  return can('season_reminders.manage') && !seasonClosed && (status === 'draft' || status === 'paused')
}

export function canPauseReminderRule(status: ReminderRuleStatus, can: Can): boolean {
  return can('season_reminders.manage') && status === 'active'
}

export function canArchiveReminderRule(status: ReminderRuleStatus, can: Can): boolean {
  return can('season_reminders.manage') && (status === 'active' || status === 'paused')
}

/* ---------------------------------------------------------------- display helpers */

export function reminderStatusBadgeClass(status: ReminderRuleStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'active':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'paused':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'archived':
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function reminderSeverityBadgeClass(severity: string): string {
  switch (severity) {
    case 'critical':
      return 'bg-red-100 text-red-900 ring-1 ring-inset ring-red-300/80'
    case 'warning':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'info':
    default:
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
  }
}

export function resolveReminderListState(options: {
  hasSeason: boolean
  isLoading: boolean
  isError: boolean
  count: number
}): ReminderViewState {
  if (!options.hasSeason) return 'need_season'
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  return options.count === 0 ? 'empty' : 'ready'
}

const KNOWN_ERROR_CODES = [
  'SEASON_REMINDER_RULE_INVALID_TRANSITION',
  'SEASON_REMINDER_RULE_NOT_EDITABLE',
  'SEASON_REMINDER_RULE_CODE_TAKEN',
  'SEASON_REMINDER_RULE_CODE_IMMUTABLE',
  'SEASON_REMINDER_RECIPIENT_PERMISSION_DENIED',
  'SEASON_REMINDER_TRIGGER_INVALID',
  'SEASON_REMINDER_SEASON_CLOSED',
  'VALIDATION_ERROR',
] as const

/** Maps a backend `code` to a locale key suffix; unknown codes fall back to `generic`. */
export function mapReminderErrorCode(code: string | undefined): string {
  return (KNOWN_ERROR_CODES as readonly string[]).includes(code ?? '') ? code! : 'generic'
}
