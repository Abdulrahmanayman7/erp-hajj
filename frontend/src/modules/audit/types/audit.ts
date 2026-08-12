export type AuditActorType = 'user' | 'system' | 'platform'
export type AuditContextType = 'tenant' | 'platform'
export type AuditSource = 'http' | 'console' | 'job' | 'scheduler'

export interface AuditDeepLink {
  available: boolean
  entity_type: string
  entity_id: number
}

export interface AuditLogSummary {
  id: number
  event_type: string
  context_type: AuditContextType
  actor_type: AuditActorType
  actor_user_id: number | null
  actor_label: string | null
  entity_type: string | null
  entity_id: number | null
  entity_number: string | null
  entity_label: string | null
  reason: string | null
  correlation_id: string
  source: AuditSource
  created_at: string
}

export interface AuditLogDetail extends AuditLogSummary {
  metadata: Record<string, unknown> | null
  before_values: Record<string, unknown> | null
  after_values: Record<string, unknown> | null
  ip_address: string | null
  user_agent: string | null
  deep_link: AuditDeepLink | null
}

export interface AuditListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ListAuditLogsParams {
  search?: string
  event_type?: string
  actor_user_id?: number | ''
  entity_type?: string
  entity_id?: number | ''
  date_from?: string
  date_to?: string
  correlation_id?: string
  page?: number
  per_page?: number
}
