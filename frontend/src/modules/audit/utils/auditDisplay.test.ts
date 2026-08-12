import { describe, expect, it } from 'vitest'

import {
  auditActorDisplay,
  auditEventLabel,
  resolveAuditDeepLink,
} from '../utils/auditDisplay'

describe('auditDisplay extras', () => {
  it('uses platform actor snapshot when present', () => {
    expect(auditActorDisplay('platform', 'Support Admin')).toBe('Support Admin')
    expect(auditActorDisplay('platform', null)).toBe('منصة')
  })

  it('falls back for unknown event codes without crashing', () => {
    expect(auditEventLabel('FUTURE_EVENT')).toBe('FUTURE_EVENT')
  })

  it('rejects unsupported entity deep links', () => {
    expect(
      resolveAuditDeepLink({ available: true, entity_type: 'unknown_thing', entity_id: 1 }),
    ).toBeNull()
  })
})
