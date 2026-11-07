import type { NotificationSeverity } from '../types/notifications'

const TYPE_LABELS_AR: Record<string, string> = {
  CONTRACT_EXPIRING_SOON: 'عقد ينتهي قريباً',
  CONTRACT_EXPIRED: 'عقد منتهٍ',
  MEETING_SCHEDULED: 'اجتماع مجدول',
  MEETING_RESCHEDULED: 'إعادة جدولة اجتماع',
  MEETING_CANCELLED: 'إلغاء اجتماع',
  MEETING_STARTING_SOON: 'اجتماع يبدأ قريباً',
  DECISION_SUBMITTED: 'قرار مُقدَّم',
  DECISION_APPROVED: 'قرار معتمد',
  DECISION_RETURNED_TO_DRAFT: 'قرار أُعيد لمسودة',
  DECISION_CLOSED: 'قرار مغلق',
  DECISION_CANCELLED: 'قرار ملغى',
  TASK_ASSIGNED: 'مهمة مُسندة',
  TASK_REASSIGNED: 'إعادة إسناد مهمة',
  TASK_COMPLETED: 'مهمة مكتملة',
  TASK_DUE_SOON: 'مهمة تستحق قريباً',
  TASK_OVERDUE: 'مهمة متأخرة',
  CUSTODY_ASSIGNED: 'عهدة مُسندة',
  CUSTODY_RETURNED: 'عهدة مُستلمة',
  CUSTODY_EXPECTED_RETURN_SOON: 'عهدة يُتوقع إرجاعها قريباً',
  CUSTODY_OVERDUE: 'عهدة متأخرة',
  STOCK_BELOW_MINIMUM: 'مخزون دون الحد الأدنى',
  SUPPLIER_EVALUATION_MONITORING_RECOMMENDED: 'توصية بمراقبة مورد',
  PURCHASE_APPROVAL_SLA_ESCALATED: 'تصعيد اعتماد طلب شراء',
  PURCHASE_APPROVAL_POOL_READY: 'طلب شراء بانتظار الاعتماد',
  PURCHASE_APPROVAL_ROUTING_BLOCKED: 'تعذّر توجيه اعتماد طلب شراء',
  PURCHASE_APPROVAL_NO_ELIGIBLE_FINAL: 'لا يوجد معتمد نهائي مؤهل',
  PURCHASE_REQUEST_RETURNED: 'أُعيد طلب الشراء للتعديل',
  PURCHASE_REQUEST_REJECTED: 'رُفض طلب الشراء',
  PURCHASE_REQUEST_APPROVED: 'اعتمد طلب الشراء',
  PURCHASE_REQUEST_CANCEL_APPROVED: 'أُلغي طلب شراء معتمد',
  PROCUREMENT_RFQ_PUBLISHED: 'نُشر طلب عروض أسعار',
  PROCUREMENT_RFQ_SUPPLIER_SELECTED: 'تمّت ترسية طلب عروض أسعار',
  PURCHASE_ORDER_ISSUED: 'صدر أمر شراء',
  GOODS_RECEIPT_POSTED: 'رُحّل سند استلام',
  PURCHASE_ORDER_CLOSED: 'أُقفل أمر شراء',
  SEASON_REMINDER_DUE: 'تذكير موسمي',
}

const SEVERITY_LABELS_AR: Record<NotificationSeverity, string> = {
  info: 'معلومات',
  warning: 'تحذير',
  critical: 'حرج',
}

export function notificationTypeLabel(type: string): string {
  return TYPE_LABELS_AR[type] ?? type
}

export function notificationSeverityLabel(severity: NotificationSeverity | string): string {
  if (severity === 'info' || severity === 'warning' || severity === 'critical') {
    return SEVERITY_LABELS_AR[severity]
  }
  return severity
}

export function notificationSeverityBadgeClass(severity: NotificationSeverity | string): string {
  switch (severity) {
    case 'warning':
      return 'bg-amber-50 text-amber-900 ring-1 ring-amber-200/70'
    case 'critical':
      return 'bg-red-50 text-red-800 ring-1 ring-red-200/70'
    case 'info':
    default:
      return 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
  }
}

export function formatNotificationRelativeTime(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  const diffMs = date.getTime() - Date.now()
  const absSec = Math.round(Math.abs(diffMs) / 1000)
  const rtf = new Intl.RelativeTimeFormat('ar', { numeric: 'auto' })

  if (absSec < 60) {
    return rtf.format(Math.round(diffMs / 1000), 'second')
  }
  const absMin = Math.round(absSec / 60)
  if (absMin < 60) {
    return rtf.format(Math.round(diffMs / 60_000), 'minute')
  }
  const absHour = Math.round(absMin / 60)
  if (absHour < 24) {
    return rtf.format(Math.round(diffMs / 3_600_000), 'hour')
  }
  const absDay = Math.round(absHour / 24)
  if (absDay < 30) {
    return rtf.format(Math.round(diffMs / 86_400_000), 'day')
  }
  return date.toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

export function formatNotificationAbsoluteTime(iso: string | null | undefined): string {
  if (!iso) {
    return ''
  }
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return date.toLocaleString('ar-SA', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
