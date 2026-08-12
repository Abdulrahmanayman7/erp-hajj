import type { AuditActorType, AuditDeepLink, AuditLogSummary } from '../types/audit'

/** Canonical event code → Arabic label (stable codes stay English in API/DB). */
export const AUDIT_EVENT_LABELS: Record<string, string> = {
  LOGIN_SUCCESS: 'تسجيل دخول ناجح',
  LOGIN_FAILED: 'فشل تسجيل الدخول',
  LOGOUT: 'تسجيل خروج',
  PASSWORD_RESET_REQUESTED: 'طلب إعادة تعيين كلمة المرور',
  PASSWORD_RESET_COMPLETED: 'اكتمال إعادة تعيين كلمة المرور',
  ACCOUNT_DISABLED_ACCESS_ATTEMPT: 'محاولة دخول لحساب معطّل',
  TENANT_BLOCKED_ACCESS_ATTEMPT: 'محاولة دخول لمنشأة محظورة',
  USER_CREATED: 'إنشاء مستخدم',
  USER_UPDATED: 'تحديث مستخدم',
  USER_ENABLED: 'تفعيل مستخدم',
  USER_DISABLED: 'تعطيل مستخدم',
  USER_ROLES_CHANGED: 'تغيير أدوار مستخدم',
  ROLE_CREATED: 'إنشاء دور',
  ROLE_UPDATED: 'تحديث دور',
  ROLE_ACTIVATED: 'تفعيل دور',
  ROLE_DEACTIVATED: 'تعطيل دور',
  ROLE_DELETED: 'حذف دور',
  ROLE_PERMISSIONS_CHANGED: 'تغيير صلاحيات دور',
  PERMISSION_CATALOG_SYNCED: 'مزامنة كتالوج الصلاحيات',
  CONTRACT_CREATED: 'إنشاء عقد',
  CONTRACT_UPDATED: 'تحديث عقد',
  CONTRACT_APPROVED: 'تمت الموافقة على عقد',
  CONTRACT_SIGNED: 'توقيع عقد',
  CONTRACT_EXECUTED: 'تنفيذ عقد',
  CONTRACT_CLOSED: 'إغلاق عقد',
  CONTRACT_CANCELLED: 'إلغاء عقد',
  CONTRACT_RENEWED: 'تجديد عقد',
  MEETING_CREATED: 'إنشاء اجتماع',
  MEETING_COMPLETED: 'إكمال اجتماع',
  MEETING_CANCELLED: 'إلغاء اجتماع',
  DECISION_CREATED: 'إنشاء قرار',
  DECISION_APPROVED: 'اعتماد قرار',
  DECISION_CLOSED: 'إغلاق قرار',
  TASK_CREATED: 'إنشاء مهمة',
  TASK_ASSIGNED: 'تم إسناد مهمة',
  TASK_REASSIGNED: 'إعادة إسناد مهمة',
  TASK_COMPLETED: 'إكمال مهمة',
  DOCUMENT_UPLOADED: 'رفع مستند',
  DOCUMENT_DOWNLOADED: 'تم تنزيل مستند',
  DOCUMENT_DELETED: 'حذف مستند',
  STOCK_RECEIVED: 'استلام مخزون',
  STOCK_ISSUED: 'صرف مخزون',
  STOCK_RETURNED: 'إرجاع مخزون',
  STOCK_TRANSFERRED: 'تحويل مخزون',
  STOCK_ADJUSTED: 'تسوية مخزون',
  ASSET_CREATED: 'تسجيل أصل',
  ASSET_ASSIGNED: 'تسليم عهدة',
  ASSET_RETURNED: 'استلام عهدة',
  ASSET_RETIRED: 'استبعاد أصل',
}

export const AUDIT_FIELD_LABELS: Record<string, string> = {
  status: 'الحالة',
  title: 'العنوان',
  name: 'الاسم',
  email: 'البريد',
  quantity: 'الكمية',
  reason: 'السبب',
}

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
  return AUDIT_EVENT_LABELS[eventType] ?? eventType
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
  return row.entity_type ?? '—'
}

export function auditFieldLabel(key: string): string {
  return AUDIT_FIELD_LABELS[key] ?? key.replaceAll('_', ' ')
}

export function formatAuditValue(value: unknown): string {
  if (value === null || value === undefined) {
    return '—'
  }
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return String(value)
  }
  try {
    return JSON.stringify(value)
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
