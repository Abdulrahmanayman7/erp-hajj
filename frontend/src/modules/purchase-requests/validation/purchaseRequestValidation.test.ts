import { describe, expect, it } from 'vitest'

import {
  mapPurchaseRequestErrorCode,
  priorityNeedsJustification,
  validateCreatePurchaseRequest,
} from './purchaseRequestValidation'

describe('purchaseRequestValidation', () => {
  it('maps known error codes', () => {
    expect(mapPurchaseRequestErrorCode('PR_DUPLICATE_ACK_REQUIRED')).toBe(
      'purchaseRequests.errors.duplicateAck',
    )
    expect(mapPurchaseRequestErrorCode('UNKNOWN')).toBe('purchaseRequests.errors.generic')
  })

  it('requires urgency justification for urgent priority', () => {
    expect(priorityNeedsJustification('urgent')).toBe(true)
    const errors = validateCreatePurchaseRequest({
      request_kind: 'administrative',
      title: 'طلب',
      purpose: 'غرض',
      priority: 'urgent',
      beneficiary_organization_unit_id: 1,
      lines: [
        {
          description: 'بند',
          quantity: 1,
          unit: 'قطعة',
          need_date: '2026-12-01',
          unit_estimate: 10,
        },
      ],
    })
    expect(errors.urgency_justification).toBe('required')
  })

  it('requires season for seasonal kind', () => {
    const errors = validateCreatePurchaseRequest({
      request_kind: 'seasonal',
      title: 'طلب',
      purpose: 'غرض',
      priority: 'normal',
      beneficiary_organization_unit_id: 1,
      lines: [
        {
          description: 'بند',
          quantity: 1,
          unit: 'قطعة',
          need_date: '2026-12-01',
          unit_estimate: 10,
        },
      ],
    })
    expect(errors.season_id).toBe('required')
  })

  it('requires unpriced reason when no estimate', () => {
    const errors = validateCreatePurchaseRequest({
      request_kind: 'administrative',
      title: 'طلب',
      purpose: 'غرض',
      priority: 'normal',
      beneficiary_organization_unit_id: 1,
      lines: [
        {
          description: 'بند',
          quantity: 1,
          unit: 'قطعة',
          need_date: '2026-12-01',
        },
      ],
    })
    expect(errors['lines.0.unpriced_reason']).toBe('required')
  })
})
