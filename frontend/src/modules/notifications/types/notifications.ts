export type NotificationSeverity = 'info' | 'warning' | 'critical'

export const NOTIFICATION_SEVERITIES: NotificationSeverity[] = ['info', 'warning', 'critical']

/** Catalog types from the notifications module API. */
export type NotificationType =
  | 'CONTRACT_EXPIRING_SOON'
  | 'CONTRACT_EXPIRED'
  | 'MEETING_SCHEDULED'
  | 'MEETING_RESCHEDULED'
  | 'MEETING_CANCELLED'
  | 'MEETING_STARTING_SOON'
  | 'DECISION_SUBMITTED'
  | 'DECISION_APPROVED'
  | 'DECISION_RETURNED_TO_DRAFT'
  | 'DECISION_CLOSED'
  | 'DECISION_CANCELLED'
  | 'TASK_ASSIGNED'
  | 'TASK_REASSIGNED'
  | 'TASK_COMPLETED'
  | 'TASK_DUE_SOON'
  | 'TASK_OVERDUE'
  | 'CUSTODY_ASSIGNED'
  | 'CUSTODY_RETURNED'
  | 'CUSTODY_EXPECTED_RETURN_SOON'
  | 'CUSTODY_OVERDUE'
  | 'STOCK_BELOW_MINIMUM'

export const NOTIFICATION_TYPES: NotificationType[] = [
  'CONTRACT_EXPIRING_SOON',
  'CONTRACT_EXPIRED',
  'MEETING_SCHEDULED',
  'MEETING_RESCHEDULED',
  'MEETING_CANCELLED',
  'MEETING_STARTING_SOON',
  'DECISION_SUBMITTED',
  'DECISION_APPROVED',
  'DECISION_RETURNED_TO_DRAFT',
  'DECISION_CLOSED',
  'DECISION_CANCELLED',
  'TASK_ASSIGNED',
  'TASK_REASSIGNED',
  'TASK_COMPLETED',
  'TASK_DUE_SOON',
  'TASK_OVERDUE',
  'CUSTODY_ASSIGNED',
  'CUSTODY_RETURNED',
  'CUSTODY_EXPECTED_RETURN_SOON',
  'CUSTODY_OVERDUE',
  'STOCK_BELOW_MINIMUM',
]

export type NotificationEntityType =
  | 'contract'
  | 'meeting'
  | 'decision'
  | 'task'
  | 'asset'
  | 'custody'
  | 'inventory_item'
  | 'warehouse'

export interface Notification {
  id: number
  type: string
  title: string
  body: string
  severity: NotificationSeverity
  entity_type: string | null
  entity_id: number | null
  read_at: string | null
  created_at: string | null
  is_read: boolean
}

export interface NotificationsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface UnreadCountResponse {
  unread_count: number
}

export interface ListNotificationsParams {
  unread_only?: boolean | 1 | '1' | ''
  type?: string
  severity?: NotificationSeverity | ''
  created_from?: string
  created_to?: string
  page?: number
  per_page?: number
}

/** Recent items shown in the topbar bell panel. */
export const BELL_RECENT_PER_PAGE = 10
