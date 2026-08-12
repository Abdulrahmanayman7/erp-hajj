import { describe, expect, it } from 'vitest'

import { auditParamsToQuery } from '../api/auditApi'
import {
  auditActorDisplay,
  auditEntityDisplay,
  auditEventLabel,
  auditFieldLabel,
  formatAuditValue,
  resolveAuditDeepLink,
} from '../utils/auditDisplay'

describe('auditDisplay', () => {
  it('maps known event codes to Arabic labels', () => {
    expect(auditEventLabel('TASK_ASSIGNED')).toBe('تم إسناد مهمة')
    expect(auditEventLabel('DOCUMENT_DOWNLOADED')).toBe('تم تنزيل مستند')
    expect(auditEventLabel('UNKNOWN_EVENT_X')).toBe('UNKNOWN_EVENT_X')
  })

  it('renders system actor as النظام', () => {
    expect(auditActorDisplay('system', null)).toBe('النظام')
    expect(auditActorDisplay('user', 'أحمد')).toBe('أحمد')
  })

  it('formats entity snapshots', () => {
    expect(
      auditEntityDisplay({
        entity_number: 'TSK-000001',
        entity_label: 'مهمة',
        entity_type: 'task',
      }),
    ).toBe('TSK-000001 — مهمة')
  })

  it('formats before/after field labels and values', () => {
    expect(auditFieldLabel('status')).toBe('الحالة')
    expect(formatAuditValue('draft')).toBe('draft')
    expect(formatAuditValue({ a: 1 })).toContain('a')
  })

  it('resolves deep links only when available', () => {
    expect(resolveAuditDeepLink(null)).toBeNull()
    expect(resolveAuditDeepLink({ available: false, entity_type: 'task', entity_id: 1 })).toBeNull()
    expect(resolveAuditDeepLink({ available: true, entity_type: 'task', entity_id: 44 })).toBe(
      '/app/tasks/44',
    )
  })
})

describe('auditApi query serialization', () => {
  it('omits empty filters', () => {
    expect(auditParamsToQuery({ search: '', event_type: 'TASK_ASSIGNED', page: 1 })).toBe(
      '?event_type=TASK_ASSIGNED&page=1',
    )
  })
})
