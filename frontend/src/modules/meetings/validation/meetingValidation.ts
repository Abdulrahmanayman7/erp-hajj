import type {
  AttendanceStatus,
  MeetingFormState,
  MeetingLifecycleAction,
  MeetingLocationType,
  MeetingStatus,
  RecommendationStatus,
} from '../types/meetings'

export interface MeetingFieldErrors {
  title?: string
  scheduled_at?: string
}

export interface AgendaItemFieldErrors {
  title?: string
}

export interface RecommendationFieldErrors {
  title?: string
}

export function validateMeetingForm(form: MeetingFormState): MeetingFieldErrors {
  const errors: MeetingFieldErrors = {}

  if (!form.title.trim()) {
    errors.title = 'required'
  }

  return errors
}

export function validateAgendaItemForm(form: { title: string }): AgendaItemFieldErrors {
  const errors: AgendaItemFieldErrors = {}
  if (!form.title.trim()) {
    errors.title = 'required'
  }
  return errors
}

export function validateRecommendationForm(form: { title: string }): RecommendationFieldErrors {
  const errors: RecommendationFieldErrors = {}
  if (!form.title.trim()) {
    errors.title = 'required'
  }
  return errors
}

/** Stable API error codes → i18n keys under meetings.errors.* */
export function mapMeetingErrorCode(code: string | undefined): string {
  switch (code) {
    case 'MEETING_NUMBER_TAKEN':
    case 'MEETING_INVALID_STATUS_TRANSITION':
    case 'MEETING_INVALID_SCHEDULE':
    case 'MEETING_COMMENT_REQUIRED':
    case 'MEETING_NOT_EDITABLE':
    case 'MEETING_DELETE_FORBIDDEN':
    case 'MEETING_EMPLOYEE_INVALID':
    case 'MEETING_ORGANIZATION_INVALID':
    case 'MEETING_ATTENDEE_DUPLICATE':
    case 'MEETING_ATTENDEE_INVALID':
    case 'MEETING_MINUTES_REQUIRED':
    case 'MEETING_COMPLETION_REQUIREMENTS_NOT_MET':
    case 'MEETING_RECOMMENDATION_NOT_FOUND':
    case 'MEETING_AGENDA_ITEM_INVALID':
      return code
    default:
      return 'generic'
  }
}

export type MeetingsListViewState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveMeetingsListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
}): MeetingsListViewState {
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function canShowCreateMeetingCta(permissions: string[]): boolean {
  return permissions.includes('meetings.create')
}

export function isMeetingLocked(status: MeetingStatus): boolean {
  return status === 'completed' || status === 'cancelled'
}

export function canEditMeeting(status: MeetingStatus, permissions: string[]): boolean {
  return !isMeetingLocked(status) && permissions.includes('meetings.update')
}

export function canDeleteMeeting(status: MeetingStatus, permissions: string[]): boolean {
  return status === 'draft' && permissions.includes('meetings.update')
}

export interface LifecycleActionDef {
  action: MeetingLifecycleAction
  permission: string
  requiresComment: boolean
  requiresDatetime: boolean
}

/**
 * UX-only helper: which lifecycle buttons to offer for a status.
 * Backend remains the authority for transition validity.
 */
export function availableLifecycleActions(status: MeetingStatus): LifecycleActionDef[] {
  switch (status) {
    case 'draft':
      return [
        {
          action: 'schedule',
          permission: 'meetings.update',
          requiresComment: false,
          requiresDatetime: true,
        },
        {
          action: 'cancel',
          permission: 'meetings.cancel',
          requiresComment: true,
          requiresDatetime: false,
        },
      ]
    case 'scheduled':
      return [
        {
          action: 'reschedule',
          permission: 'meetings.update',
          requiresComment: false,
          requiresDatetime: true,
        },
        {
          action: 'start',
          permission: 'meetings.update',
          requiresComment: false,
          requiresDatetime: false,
        },
        {
          action: 'cancel',
          permission: 'meetings.cancel',
          requiresComment: true,
          requiresDatetime: false,
        },
      ]
    case 'in_progress':
      return [
        {
          action: 'complete',
          permission: 'meetings.update',
          requiresComment: false,
          requiresDatetime: false,
        },
        {
          action: 'cancel',
          permission: 'meetings.cancel',
          requiresComment: true,
          requiresDatetime: false,
        },
      ]
    default:
      return []
  }
}

export function meetingStatusBadgeClass(status: MeetingStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-100 text-slate-800 ring-1 ring-inset ring-slate-300/80'
    case 'scheduled':
      return 'bg-sky-100 text-sky-950 ring-1 ring-inset ring-sky-300/80'
    case 'in_progress':
      return 'bg-amber-100 text-amber-950 ring-1 ring-inset ring-amber-300/80'
    case 'completed':
      return 'bg-emerald-100 text-emerald-950 ring-1 ring-inset ring-emerald-300/80'
    case 'cancelled':
      return 'bg-red-100 text-red-950 ring-1 ring-inset ring-red-300/80'
    default:
      return 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-300/80'
  }
}

export function meetingStatusDotClass(status: MeetingStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-slate-500'
    case 'scheduled':
      return 'bg-sky-500'
    case 'in_progress':
      return 'bg-amber-500'
    case 'completed':
      return 'bg-emerald-500'
    case 'cancelled':
      return 'bg-red-500'
    default:
      return 'bg-neutral-400'
  }
}

export function isMeetingToday(scheduledAt: string | null | undefined): boolean {
  if (!scheduledAt) return false
  const date = new Date(scheduledAt)
  if (Number.isNaN(date.getTime())) return false
  const now = new Date()
  return (
    date.getFullYear() === now.getFullYear() &&
    date.getMonth() === now.getMonth() &&
    date.getDate() === now.getDate()
  )
}

/** Convert API ISO datetime to datetime-local input value (local wall clock). */
export function toDatetimeLocalValue(iso: string | null | undefined): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** Convert datetime-local value to ISO string for API payloads. */
export function fromDatetimeLocalValue(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed) return null
  const date = new Date(trimmed)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString()
}

export const MEETING_STATUSES: MeetingStatus[] = [
  'draft',
  'scheduled',
  'in_progress',
  'completed',
  'cancelled',
]

export const MEETING_LOCATION_TYPES: MeetingLocationType[] = ['physical', 'remote', 'hybrid']

export const ATTENDANCE_STATUSES: AttendanceStatus[] = [
  'invited',
  'attended',
  'absent',
  'excused',
]

export const RECOMMENDATION_STATUSES: RecommendationStatus[] = ['draft', 'final']
