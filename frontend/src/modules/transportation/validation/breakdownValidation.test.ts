import { describe, expect, it } from 'vitest'

import {
  emptyBreakdownForm,
  mapBreakdownErrorCode,
  validateBreakdownForm,
  validateReplacementForm,
} from './breakdownValidation'

describe('breakdownValidation', () => {
  it('requires a reason of at least 3 chars', () => {
    expect(validateBreakdownForm(emptyBreakdownForm())).toEqual({ reason: 'min' })
    expect(validateBreakdownForm({ reason: 'عطل', notes: '' })).toEqual({})
  })

  it('requires a reassignment plan when capacity gap exists', () => {
    expect(
      validateReplacementForm(
        { replacement_season_vehicle_id: 9, reason: 'استبدال بسبب عطل' },
        { planRequired: true, planCount: 0, capacityGap: 2, requireReason: true },
      ),
    ).toEqual({ plan: 'insufficient' })
  })

  it('maps domain error codes', () => {
    expect(mapBreakdownErrorCode('TRANSPORT_VEHICLE_OVERLAP')).toBe('overlap')
    expect(mapBreakdownErrorCode('TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED')).toBe('planRequired')
    expect(mapBreakdownErrorCode('SEASON_CLOSED')).toBe('seasonClosed')
  })
})
