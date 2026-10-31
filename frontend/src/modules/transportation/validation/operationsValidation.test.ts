import { describe, expect, it } from 'vitest'

import {
  emptyMovementForm,
  mapOperationsErrorCode,
  movementActionsFor,
  runActionsFor,
  validateCreateRunForm,
  validateMovementForm,
} from './operationsValidation'

describe('validateMovementForm', () => {
  it('requires points and times', () => {
    expect(validateMovementForm(emptyMovementForm())).toEqual({
      origin_point_id: 'required',
      destination_point_id: 'required',
      planned_departure_at: 'required',
      planned_arrival_at: 'required',
    })
  })

  it('rejects same origin/destination', () => {
    const errors = validateMovementForm({
      origin_point_id: 1,
      destination_point_id: 1,
      planned_departure_at: '2027-05-01T08:00',
      planned_arrival_at: '2027-05-01T10:00',
      notes: '',
    })
    expect(errors.destination_point_id).toBe('samePoint')
  })

  it('rejects arrival before departure', () => {
    const errors = validateMovementForm({
      origin_point_id: 1,
      destination_point_id: 2,
      planned_departure_at: '2027-05-01T10:00',
      planned_arrival_at: '2027-05-01T08:00',
      notes: '',
    })
    expect(errors.planned_arrival_at).toBe('afterDeparture')
  })
})

describe('validateCreateRunForm', () => {
  it('requires season vehicle', () => {
    expect(validateCreateRunForm({ transport_season_vehicle_id: '', capacity_override: '', notes: '' })).toEqual({
      transport_season_vehicle_id: 'required',
    })
  })

  it('validates optional capacity override range', () => {
    expect(
      validateCreateRunForm({ transport_season_vehicle_id: 3, capacity_override: '0', notes: '' }),
    ).toEqual({ capacity_override: 'range' })
  })
})

describe('lifecycle action maps', () => {
  it('exposes only backend movement transitions', () => {
    expect(movementActionsFor('draft')).toEqual(['plan', 'cancel'])
    expect(movementActionsFor('planned')).toEqual(['start', 'cancel'])
    expect(movementActionsFor('in_progress')).toEqual(['complete'])
    expect(movementActionsFor('completed')).toEqual([])
  })

  it('exposes only backend run transitions', () => {
    expect(runActionsFor('planned')).toEqual(['start-boarding', 'cancel'])
    expect(runActionsFor('boarding')).toEqual(['depart', 'cancel'])
    expect(runActionsFor('departed')).toEqual(['arrive'])
    expect(runActionsFor('arrived')).toEqual(['complete'])
  })
})

describe('mapOperationsErrorCode', () => {
  it('maps capacity and overlap codes', () => {
    expect(mapOperationsErrorCode('TRANSPORT_CAPACITY_EXCEEDED')).toBe('capacity')
    expect(mapOperationsErrorCode('TRANSPORT_RESOURCE_OVERLAP')).toBe('overlap')
    expect(mapOperationsErrorCode('SEASON_CLOSED')).toBe('seasonClosed')
  })
})
