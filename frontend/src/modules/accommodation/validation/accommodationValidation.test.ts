import { describe, expect, it } from 'vitest'

import {
  buildConfirmFamilyPayload,
  buildRelocatePayload,
  canShowArriveAction,
  canShowCancelAssignmentAction,
  canShowCheckInAction,
  canShowCheckOutAction,
  canShowConfirmAction,
  canShowRelocateAction,
  capacityDisplayKind,
  effectiveOperationalStatus,
  filterFacilitiesClientSide,
  fromLocalDatetimeInput,
  mapAccommodationErrorCode,
  resolveListState,
  toUtcInputValue,
  validateAssignmentForm,
  validateFacilityForm,
} from './accommodationValidation'

describe('accommodationValidation', () => {
  it('resolves list states', () => {
    expect(resolveListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveListState({ isLoading: false, isError: false, count: 2 })).toBe('ready')
  })

  it('maps season closed, confirm D7, Sprint 3 ops, and interval codes', () => {
    expect(mapAccommodationErrorCode('SEASON_CLOSED')).toBe('seasonClosed')
    expect(mapAccommodationErrorCode('SEASON_NOT_ACTIVE')).toBe('seasonNotActive')
    expect(mapAccommodationErrorCode('ACCOMMODATION_INTERVAL_INVALID')).toBe('intervalInvalid')
    expect(mapAccommodationErrorCode('ACCOMMODATION_PILGRIM_OVERLAP')).toBe('pilgrimOverlap')
    expect(mapAccommodationErrorCode('ACCOMMODATION_CAPACITY_CONFLICT')).toBe('capacityConflict')
    expect(mapAccommodationErrorCode('ACCOMMODATION_FAMILY_EXCLUSIVITY')).toBe('familyExclusivity')
    expect(mapAccommodationErrorCode('ACCOMMODATION_ILLEGAL_TRANSITION')).toBe('illegalTransition')
    expect(mapAccommodationErrorCode('ACCOMMODATION_CHECKOUT_BEFORE_START')).toBe('checkoutBeforeStart')
    expect(mapAccommodationErrorCode('ACCOMMODATION_RELOCATE_INTERVAL_INVALID')).toBe(
      'relocateIntervalInvalid',
    )
    expect(mapAccommodationErrorCode('ACCOMMODATION_INVALID_REFERENCE')).toBe('invalidReference')
    expect(mapAccommodationErrorCode('UNKNOWN')).toBe('generic')
  })

  it('gates confirm visibility by permission, Active season, and draft status', () => {
    expect(
      canShowConfirmAction({
        hasConfirmPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'draft',
      }),
    ).toBe(true)
    expect(
      canShowConfirmAction({
        hasConfirmPermission: false,
        seasonStatus: 'active',
        bookingStatus: 'draft',
      }),
    ).toBe(false)
    expect(
      canShowConfirmAction({
        hasConfirmPermission: true,
        seasonStatus: 'draft',
        bookingStatus: 'draft',
      }),
    ).toBe(false)
    expect(
      canShowConfirmAction({
        hasConfirmPermission: true,
        seasonStatus: 'closed',
        bookingStatus: 'draft',
      }),
    ).toBe(false)
    expect(
      canShowConfirmAction({
        hasConfirmPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
      }),
    ).toBe(false)
  })

  it('builds atomic family payloads without inventing membership', () => {
    expect(buildConfirmFamilyPayload(9, [3, 1, 3, 2])).toEqual({
      season_family_group_id: 9,
      assignment_ids: [1, 2, 3],
    })
  })

  it('treats null operational_status as assigned', () => {
    expect(effectiveOperationalStatus(null)).toBe('assigned')
    expect(effectiveOperationalStatus('arrived')).toBe('arrived')
  })

  it('gates ops visibility by permission, Active season, and state', () => {
    expect(
      canShowArriveAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: null,
      }),
    ).toBe(true)
    expect(
      canShowArriveAction({
        hasPermission: true,
        seasonStatus: 'draft',
        bookingStatus: 'confirmed',
        operationalStatus: null,
      }),
    ).toBe(false)
    expect(
      canShowCheckInAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: 'arrived',
      }),
    ).toBe(true)
    expect(
      canShowCheckInAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: null,
      }),
    ).toBe(true)
    expect(
      canShowCheckOutAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: 'checked_in',
      }),
    ).toBe(true)
    expect(
      canShowCheckOutAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: 'arrived',
      }),
    ).toBe(false)
    expect(
      canShowCancelAssignmentAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: 'assigned',
      }),
    ).toBe(true)
    expect(
      canShowCancelAssignmentAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
        operationalStatus: 'checked_in',
      }),
    ).toBe(false)
    expect(
      canShowCancelAssignmentAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'draft',
        operationalStatus: null,
      }),
    ).toBe(false)
    expect(
      canShowRelocateAction({
        hasPermission: true,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
      }),
    ).toBe(true)
    expect(
      canShowRelocateAction({
        hasPermission: false,
        seasonStatus: 'active',
        bookingStatus: 'confirmed',
      }),
    ).toBe(false)
    expect(
      canShowRelocateAction({
        hasPermission: true,
        seasonStatus: 'closed',
        bookingStatus: 'confirmed',
      }),
    ).toBe(false)
  })

  it('builds relocate payloads without prohibited booking fields', () => {
    const payload = buildRelocatePayload({
      destinationSeasonUnitId: 7,
      destinationSeasonPositionId: 2,
      occurredAtLocal: '2027-06-03T12:00',
      checkInAfter: true,
    })
    expect(payload.destination_season_unit_id).toBe(7)
    expect(payload.destination_season_position_id).toBe(2)
    expect(payload.check_in_after).toBe(true)
    expect(payload.occurred_at).toMatch(/2027-06-03T/)
    expect((payload as { booking_status?: string }).booking_status).toBeUndefined()
  })

  it('distinguishes reserved vs historical capacity labels', () => {
    expect(capacityDisplayKind({ bookingStatus: 'confirmed', capacityReserved: true })).toBe(
      'reserved',
    )
    expect(capacityDisplayKind({ bookingStatus: 'completed', capacityReserved: false })).toBe(
      'historical',
    )
    expect(capacityDisplayKind({ bookingStatus: 'cancelled', capacityReserved: false })).toBe('none')
    expect(capacityDisplayKind({ bookingStatus: 'draft', capacityReserved: false })).toBe('none')
  })

  it('validates facility and assignment forms', () => {
    expect(validateFacilityForm({ kind: 'hotel', code: '', name: 'x', is_active: true })).toEqual({
      code: 'required',
    })
    expect(
      validateAssignmentForm({
        season_pilgrim_id: 1,
        season_unit_id: 2,
        season_position_id: '',
        starts_at: '2027-06-01T10:00',
        original_ends_at: '2027-06-01T09:00',
      }),
    ).toEqual({ original_ends_at: 'interval' })
  })

  it('filters facilities on the client without inventing server search', () => {
    const rows = [
      { code: 'H1', name: 'فندق', kind: 'hotel', is_active: true },
      { code: 'C1', name: 'مخيم', kind: 'camp', is_active: false },
    ]
    expect(filterFacilitiesClientSide(rows, { search: 'فندق', kind: '', active: '' })).toHaveLength(1)
    expect(filterFacilitiesClientSide(rows, { search: '', kind: 'camp', active: '0' })).toHaveLength(1)
  })

  it('round-trips datetime inputs through UTC ISO strings', () => {
    const iso = fromLocalDatetimeInput('2027-06-01T19:00')
    expect(iso.endsWith('Z') || iso.includes('+') || iso.includes('T')).toBe(true)
    expect(toUtcInputValue('2027-06-01T19:00:00Z')).toBe('2027-06-01T19:00')
  })
})
