import { ar } from '@/locales/ar'
import { formatIsoTimestampAr } from '@/shared/utils/gregorianDate'
import type { AuditActorType, AuditDeepLink, AuditLogSummary } from '../types/audit'

/** Canonical event code → Arabic label (stable codes stay English in API/DB). */
export const AUDIT_EVENT_LABELS: Record<string, string> = ar.audit.events

export const AUDIT_FIELD_LABELS: Record<string, string> = ar.audit.fieldLabels

const ENTITY_ROUTES: Record<string, (id: number) => string> = {
  contract: (id) => `/app/contracts/${id}`,
  meeting: (id) => `/app/meetings/${id}`,
  decision: (id) => `/app/decisions/${id}`,
  task: (id) => `/app/tasks/${id}`,
  document: (id) => `/app/documents/${id}`,
  employee: (id) => `/app/employees/${id}`,
  organization_unit: (_id) => `/app/organization`,
  warehouse: (id) => `/app/warehouses/${id}`,
  inventory_item: (id) => `/app/inventory/items/${id}`,
  asset: (id) => `/app/assets/${id}`,
  custody: (_id) => `/app/assets`,
  user: (_id) => `/app/users`,
}

export function auditEventLabel(eventType: string): string {
  return AUDIT_EVENT_LABELS[eventType] ?? ar.audit.unknownEvent
}

export function auditActorDisplay(actorType: AuditActorType, actorLabel: string | null): string {
  if (actorType === 'system') {
    return 'النظام'
  }
  if (actorType === 'platform') {
    return actorLabel?.trim() || 'منصة'
  }
  return actorLabel?.trim() || '—'
}

export function auditEntityDisplay(row: Pick<AuditLogSummary, 'entity_number' | 'entity_label' | 'entity_type'>): string {
  const parts = [row.entity_number, row.entity_label].filter((part): part is string => !!part && part.trim() !== '')
  if (parts.length > 0) {
    return parts.join(' — ')
  }
  return (ar.audit.entities as Record<string, string>)[row.entity_type ?? ''] ?? ar.audit.unknownEntity
}

export function auditFieldLabel(key: string): string {
  return AUDIT_FIELD_LABELS[key] ?? ar.audit.unknownField
}

export function formatAuditValue(value: unknown, key = '', timezone = 'Asia/Riyadh'): string {
  if (value === null || value === undefined) {
    return '—'
  }
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    if (typeof value === 'boolean') return value ? ar.audit.values.true : ar.audit.values.false
    if (typeof value === 'string' && /(_at|_on)$/.test(key) && /^\d{4}-\d{2}-\d{2}/.test(value)) return formatIsoTimestampAr(value, timezone)
    if (typeof value === 'string' && /^(status|booking_status|operational_status|audience|allocation_mode|kind|unit_kind|source|context_type)$/.test(key)) return (ar.audit.values as Record<string, string>)[value] ?? value
    return String(value)
  }
  try {
    if (Array.isArray(value)) return value.map(item => formatAuditValue(item, key, timezone)).join('، ')
    return Object.entries(value as Record<string, unknown>).map(([field, item]) => auditFieldLabel(field) + ': ' + formatAuditValue(item, field, timezone)).join('؛ ')
  } catch {
    return '—'
  }
}

export function resolveAuditDeepLink(deepLink: AuditDeepLink | null | undefined): string | null {
  if (!deepLink?.available) {
    return null
  }
  const builder = ENTITY_ROUTES[deepLink.entity_type]
  if (!builder) {
    return null
  }
  return builder(deepLink.entity_id)
}
