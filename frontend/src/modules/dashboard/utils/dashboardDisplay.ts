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
      return 'bg-[var(--danger-soft)] text-red-800'
    case 'warning':
      return 'bg-[var(--warning-soft)] text-amber-900'
    case 'info':
    default:
      return 'bg-[var(--primary-soft)] text-brand-primary-dark'
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

export function kpiIconClass(key: DashboardKpiKey): string {
  switch (key) {
    case 'tasks_overdue':
      return 'app-kpi-icon--overdue'
    case 'decisions_pending_approval':
      return 'app-kpi-icon--decisions'
    case 'contracts_expiring_soon':
      return 'app-kpi-icon--contracts'
    case 'inventory_attention':
      return 'app-kpi-icon--inventory'
    case 'custodies_overdue':
      return 'app-kpi-icon--custody'
    case 'meetings_today':
      return 'app-kpi-icon--meetings'
    default:
      return 'app-kpi-icon--info'
  }
}

export function dashboardHour(timezone?: string | null): number | null {
  try {
    const hour = Number(
      new Intl.DateTimeFormat('en-GB', {
        hour: 'numeric',
        hour12: false,
        timeZone: timezone || 'Asia/Riyadh',
      }).format(new Date()),
    )
    return Number.isFinite(hour) ? hour : null
  } catch {
    return null
  }
}

export function isDashboardDaytime(hour: number | null): boolean {
  if (hour == null) return true
  return hour >= 6 && hour < 18
}

export interface DashboardCalendarParts {
  weekday: string
  day: string
  gregorian: string
  hijri: string | null
}

function formatWithCalendar(
  date: Date,
  options: Intl.DateTimeFormatOptions,
  calendar?: string,
): string {
  const locale = calendar ? `ar-SA-u-ca-${calendar}` : 'ar-SA'
  return new Intl.DateTimeFormat(locale, options).format(date)
}

export function formatDashboardCalendar(timezone?: string | null): DashboardCalendarParts {
  const timeZone = timezone || 'Asia/Riyadh'
  const now = new Date()
  const base: Intl.DateTimeFormatOptions = { timeZone }

  let weekday = ''
  let day = ''
  let gregorian = ''
  try {
    weekday = formatWithCalendar(now, { ...base, weekday: 'long' }, 'gregory')
    day = formatWithCalendar(now, { ...base, day: 'numeric' }, 'gregory')
    gregorian = formatWithCalendar(
      now,
      { ...base, day: 'numeric', month: 'long', year: 'numeric' },
      'gregory',
    )
  } catch {
    weekday = new Intl.DateTimeFormat('ar-SA', { weekday: 'long' }).format(now)
    day = new Intl.DateTimeFormat('ar-SA', { day: 'numeric' }).format(now)
    gregorian = new Intl.DateTimeFormat('ar-SA', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(now)
  }

  let hijri: string | null = null
  for (const calendar of ['islamic-umalqura', 'islamic'] as const) {
    try {
      const label = formatWithCalendar(
        now,
        { ...base, day: 'numeric', month: 'long', year: 'numeric' },
        calendar,
      )
      if (label && label !== gregorian) {
        hijri = label
        break
      }
    } catch {
      /* Intl calendar not available in this engine */
    }
  }

  return { weekday, day, gregorian, hijri }
}

export function formatDashboardDate(timezone?: string | null): string {
  const parts = formatDashboardCalendar(timezone)
  return [parts.weekday, parts.gregorian].filter(Boolean).join(' · ')
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
