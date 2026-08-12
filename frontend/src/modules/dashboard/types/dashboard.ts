/** Dashboard severity levels from API.md */
export type DashboardSeverity = 'critical' | 'warning' | 'info'

/** Single KPI card payload */
export interface DashboardKpi {
  value: number
  label: string
  severity: DashboardSeverity
  href: string
}

/** All approved KPI keys (any may be omitted when unauthorized) */
export type DashboardKpiKey =
  | 'tasks_overdue'
  | 'tasks_open'
  | 'tasks_due_soon'
  | 'decisions_pending_approval'
  | 'decisions_approved_open'
  | 'decisions_with_open_tasks'
  | 'contracts_executing'
  | 'contracts_expiring_soon'
  | 'contracts_expired'
  | 'meetings_today'
  | 'meetings_in_progress'
  | 'inventory_low'
  | 'inventory_out'
  | 'inventory_attention'
  | 'assets_available'
  | 'assets_in_use'
  | 'assets_maintenance'
  | 'custodies_overdue'
  | 'custodies_due_soon'

/** Sparse KPI map — only permitted keys are present */
export type DashboardKpis = Partial<Record<DashboardKpiKey, DashboardKpi>>

export interface AttentionItem {
  type: string
  severity: DashboardSeverity
  title: string
  subtitle?: string
  count?: number
  entity_type?: string | null
  entity_id?: number | null
  href: string
}

export interface TodayListItem {
  id: number
  number?: string
  title: string
  scheduled_at?: string
  due_date?: string
  end_date?: string
  status?: string
  href: string
}

export interface DashboardToday {
  meetings_today?: TodayListItem[]
  tasks_due_today?: TodayListItem[]
  contracts_expiring?: TodayListItem[]
  meetings_upcoming_7d?: TodayListItem[]
}

export interface MyTasksSummary {
  open: number
  overdue: number
  href: string
}

export interface MyCustodiesSummary {
  active: number
  overdue: number
  href: string
}

export interface DashboardWork {
  my_tasks?: MyTasksSummary
}

export interface DashboardResources {
  my_custodies?: MyCustodiesSummary
}

export interface DashboardNotifications {
  unread_count: number
  href: string
}

export interface DashboardMeta {
  generated_at: string
  timezone: string
  sections?: string[]
}

/** Full GET /dashboard `data` payload — all sections optional/partial */
export interface DashboardData {
  meta?: DashboardMeta
  kpis?: DashboardKpis
  attention?: AttentionItem[]
  today?: DashboardToday
  work?: DashboardWork
  resources?: DashboardResources
  notifications?: DashboardNotifications
}
