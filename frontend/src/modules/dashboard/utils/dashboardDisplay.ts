import type {
  DashboardData,
  DashboardKpi,
  DashboardKpiKey,
  DashboardKpis,
  DashboardSeverity,
} from '../types/dashboard'

/** Fixed top-row KPI order (UI.md) — missing keys are skipped, never reordered */
export const TOP_KPI_ORDER: readonly DashboardKpiKey[] = [
  'tasks_overdue',
  'decisions_pending_approval',
  'contracts_expiring_soon',
  'inventory_attention',
  'custodies_overdue',
  'meetings_today',
] as const

/** Secondary inventory KPI keys for Resources panel */
export const RESOURCE_INVENTORY_KPI_KEYS: readonly DashboardKpiKey[] = [
  'inventory_low',
  'inventory_out',
] as const

/** Secondary asset KPI keys for Resources panel */
export const RESOURCE_ASSET_KPI_KEYS: readonly DashboardKpiKey[] = [
  'assets_available',
  'assets_in_use',
  'assets_maintenance',
  'custodies_due_soon',
] as const

/** Work-section KPI groups — only present keys are rendered */
export const WORK_TASK_KPI_KEYS: readonly DashboardKpiKey[] = [
  'tasks_open',
  'tasks_overdue',
  'tasks_due_soon',
] as const

export const WORK_DECISION_KPI_KEYS: readonly DashboardKpiKey[] = [
  'decisions_pending_approval',
  'decisions_approved_open',
  'decisions_with_open_tasks',
] as const

export const WORK_MEETING_KPI_KEYS: readonly DashboardKpiKey[] = [
  'meetings_today',
  'meetings_in_progress',
] as const

export const WORK_CONTRACT_KPI_KEYS: readonly DashboardKpiKey[] = [
  'contracts_executing',
  'contracts_expiring_soon',
  'contracts_expired',
] as const

export interface TopKpiEntry {
  key: DashboardKpiKey
  kpi: DashboardKpi
}

/** Backend may encode empty maps as `[]`; treat those as absent. */
export function asSparseRecord<T extends object>(value: unknown): T | null {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return null
  }
  return value as T
}

export function getTopKpis(kpis: unknown): TopKpiEntry[] {
  const map = asSparseRecord<DashboardKpis>(kpis)
  if (!map) return []
  const result: TopKpiEntry[] = []
  for (const key of TOP_KPI_ORDER) {
    const kpi = map[key]
    if (kpi) {
      result.push({ key, kpi })
    }
  }
  return result
}

export function pickKpis(
  kpis: unknown,
  keys: readonly DashboardKpiKey[],
): TopKpiEntry[] {
  const map = asSparseRecord<DashboardKpis>(kpis)
  if (!map) return []
  const result: TopKpiEntry[] = []
  for (const key of keys) {
    const kpi = map[key]
    if (kpi) {
      result.push({ key, kpi })
    }
  }
  return result
}

export function hasWorkMetrics(kpis: unknown, work: unknown): boolean {
  const map = asSparseRecord<DashboardKpis>(kpis)
  const workMap = asSparseRecord<DashboardData['work'] & object>(work)
  if (workMap?.my_tasks) return true
  if (!map) return false
  return (
    WORK_TASK_KPI_KEYS.some((key) => Boolean(map[key])) ||
    WORK_DECISION_KPI_KEYS.some((key) => Boolean(map[key])) ||
    WORK_MEETING_KPI_KEYS.some((key) => Boolean(map[key])) ||
    WORK_CONTRACT_KPI_KEYS.some((key) => Boolean(map[key]))
  )
}

/** Only allow internal app paths from API hrefs */
export function isSafeAppHref(href: string | null | undefined): href is string {
  if (!href || typeof href !== 'string') return false
  const trimmed = href.trim()
  if (!trimmed.startsWith('/app')) return false
  // Reject protocol-relative and scheme smuggling
  if (trimmed.startsWith('//')) return false
  if (/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(trimmed)) return false
  return true
}

export function severityCardClass(severity: DashboardSeverity | string): string {
  switch (severity) {
    case 'critical':
      return 'border-red-200/80 bg-red-50/60'
    case 'warning':
      return 'border-amber-200/80 bg-amber-50/50'
    case 'info':
    default:
      return 'border-brand-border bg-brand-surface'
  }
}

/** A zero count is calm even when its semantic category is normally urgent. */
export function effectiveSeverity(
  severity: DashboardSeverity | string,
  value: number,
): DashboardSeverity {
  if (value <= 0) return 'info'
  if (severity === 'critical' || severity === 'warning' || severity === 'info') {
    return severity
  }
  return 'info'
}

export function severityBadgeClass(severity: DashboardSeverity | string): string {
  switch (severity) {
    case 'critical':
      return 'bg-red-50 text-red-800 ring-1 ring-red-200/70'
    case 'warning':
      return 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/70'
    case 'info':
    default:
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
  }
}

export function severityValueClass(severity: DashboardSeverity | string): string {
  switch (severity) {
    case 'critical':
      return 'text-red-800'
    case 'warning':
      return 'text-amber-900'
    case 'info':
    default:
      return 'text-brand-primary-dark'
  }
}

export function formatDashboardDate(timezone?: string | null): string {
  const options: Intl.DateTimeFormatOptions = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      ...options,
      timeZone: timezone || 'Asia/Riyadh',
    }).format(new Date())
  } catch {
    return new Intl.DateTimeFormat('ar-SA', options).format(new Date())
  }
}

export function friendlyTimezone(timezone?: string | null): string {
  const labels: Record<string, string> = {
    'Asia/Riyadh': 'الرياض',
    'Asia/Dubai': 'دبي',
    'Africa/Cairo': 'القاهرة',
    UTC: 'التوقيت العالمي',
  }
  return labels[timezone ?? ''] ?? (timezone || 'الرياض').replace(/_/g, ' ')
}

export function formatDashboardGeneratedAt(
  generatedAt?: string | null,
  timezone?: string | null,
): string {
  if (!generatedAt) return 'منذ لحظات'
  const date = new Date(generatedAt)
  if (Number.isNaN(date.getTime())) return 'منذ لحظات'
  const seconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000))
  if (seconds < 60) return 'منذ لحظات'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `منذ ${minutes} دقيقة`
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      hour: 'numeric',
      minute: '2-digit',
      timeZone: timezone || undefined,
    }).format(date)
  } catch {
    return new Intl.DateTimeFormat('ar-SA', { hour: 'numeric', minute: '2-digit' }).format(date)
  }
}

export function formatListItemTime(iso: string | null | undefined, timezone?: string | null): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      timeZone: timezone || undefined,
    }).format(date)
  } catch {
    return new Intl.DateTimeFormat('ar-SA', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  }
}

export function formatListItemDate(iso: string | null | undefined): string {
  if (!iso) return ''
  // Date-only YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    const [y, m, d] = iso.split('-').map(Number)
    const date = new Date(y!, m! - 1, d!)
    return new Intl.DateTimeFormat('ar-SA', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }).format(date)
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  return new Intl.DateTimeFormat('ar-SA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(date)
}

/** True when the actor has no operational module sections (notifications alone allowed) */
export function isOperationallyEmpty(data: Pick<
  DashboardData,
  'kpis' | 'attention' | 'today' | 'work' | 'resources'
>): boolean {
  const kpis = asSparseRecord<DashboardKpis>(data.kpis)
  const kpiCount = kpis ? Object.keys(kpis).length : 0
  if (kpiCount > 0) return false
  if (Array.isArray(data.attention) && data.attention.length > 0) return false
  // Presence of today subsection keys means module access (arrays may be empty)
  const today = asSparseRecord<DashboardData['today'] & object>(data.today)
  if (today && Object.keys(today).length > 0) return false
  const work = asSparseRecord<DashboardData['work'] & object>(data.work)
  if (work && Object.keys(work).length > 0) return false
  const resources = asSparseRecord<DashboardData['resources'] & object>(data.resources)
  if (resources && Object.keys(resources).length > 0) return false
  return true
}
