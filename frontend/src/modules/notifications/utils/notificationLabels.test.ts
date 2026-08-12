import { describe, expect, it } from 'vitest'

import {
  notificationSeverityBadgeClass,
  notificationSeverityLabel,
  notificationTypeLabel,
} from './notificationLabels'

describe('notificationLabels', () => {
  it('returns Arabic labels for known types and severities', () => {
    expect(notificationTypeLabel('TASK_ASSIGNED')).toBe('مهمة مُسندة')
    expect(notificationTypeLabel('STOCK_BELOW_MINIMUM')).toBe('مخزون دون الحد الأدنى')
    expect(notificationSeverityLabel('info')).toBe('معلومات')
    expect(notificationSeverityLabel('warning')).toBe('تحذير')
    expect(notificationSeverityLabel('critical')).toBe('حرج')
  })

  it('falls back for unknown type codes', () => {
    expect(notificationTypeLabel('UNKNOWN_TYPE')).toBe('UNKNOWN_TYPE')
  })

  it('maps severity to badge classes', () => {
    expect(notificationSeverityBadgeClass('critical')).toContain('red')
    expect(notificationSeverityBadgeClass('warning')).toContain('amber')
    expect(notificationSeverityBadgeClass('info')).toContain('emerald')
  })
})
