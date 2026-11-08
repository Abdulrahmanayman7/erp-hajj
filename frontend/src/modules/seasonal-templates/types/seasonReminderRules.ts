export type ReminderSeverity = 'info' | 'warning' | 'critical'
export const REMINDER_SEVERITIES: readonly ReminderSeverity[] = ['info', 'warning', 'critical']

export type ReminderRuleStatus = 'draft' | 'active' | 'paused' | 'archived'
export const REMINDER_RULE_STATUSES: readonly ReminderRuleStatus[] = ['draft', 'active', 'paused', 'archived']

export type ReminderTriggerMode = 'absolute_date' | 'offset_from_season_start'
export const REMINDER_TRIGGER_MODES: readonly ReminderTriggerMode[] = ['absolute_date', 'offset_from_season_start']

/**
 * UX mirror of backend `SeasonReminderRecipientPermissions::approved()` (S08-D14, 9 view permissions).
 * The server remains authoritative and re-validates on every write and on activation.
 */
export const APPROVED_RECIPIENT_PERMISSIONS = [
  'seasons.view',
  'season_templates.view',
  'season_projects.view',
  'tasks.view',
  'contracts.view',
  'meetings.view',
  'decisions.view',
  'season_copy.history',
  'season_reminders.view',
] as const

export type ApprovedRecipientPermission = (typeof APPROVED_RECIPIENT_PERMISSIONS)[number]

/** Technical sanity bound mirrored from the backend guard (MAX_OFFSET_DAYS). */
export const MAX_OFFSET_DAYS = 366

export type ReminderViewState = 'need_season' | 'loading' | 'error' | 'empty' | 'ready'

export interface ReminderRuleSeasonSummary {
  id: number
  code: string
  name: string
  status: string
}

export interface ReminderRuleUserSummary {
  id: number
  name: string
}

export interface SeasonReminderRule {
  id: number
  season_id: number
  season: ReminderRuleSeasonSummary | null
  code: string
  title: string
  body: string | null
  severity: ReminderSeverity
  status: ReminderRuleStatus
  trigger_mode: ReminderTriggerMode
  trigger_on: string | null
  offset_days: number | null
  recipient_mode: string
  recipient_permission: string
  created_by: ReminderRuleUserSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface SeasonReminderRulesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSeasonReminderRulesParams {
  status?: ReminderRuleStatus | ''
  page?: number
  per_page?: number
}

/** `status` is never client-set; the server creates every rule as a draft. */
export interface CreateSeasonReminderRulePayload {
  code: string
  title: string
  body?: string | null
  severity: ReminderSeverity
  trigger_mode: ReminderTriggerMode
  trigger_on?: string | null
  offset_days?: number | null
  recipient_permission: string
}

/** `code` is immutable and therefore never sent on update. */
export interface UpdateSeasonReminderRulePayload {
  title?: string
  body?: string | null
  severity?: ReminderSeverity
  trigger_mode?: ReminderTriggerMode
  trigger_on?: string | null
  offset_days?: number | null
  recipient_permission?: string
}

export interface ReminderRuleFormState {
  code: string
  title: string
  body: string
  severity: ReminderSeverity
  trigger_mode: ReminderTriggerMode
  trigger_on: string
  /** Kept as text so the input can be empty / partially typed. */
  offset_days: string
  recipient_permission: string
}
