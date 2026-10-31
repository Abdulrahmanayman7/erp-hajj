import { describe, expect, it } from 'vitest'

import type {
  DefaultCrewFormState,
  DriverFormState,
  SeasonVehicleFormState,
  VehicleFormState,
} from '../types/fleet'
import type { PointFormState } from '../types/points'
import {
  canManageRegistry,
  canManageSeasonFleet,
  canViewTransportation,
  emptyDefaultCrewForm,
  emptyDriverForm,
  emptyPointForm,
  emptySeasonVehicleForm,
  emptyVehicleForm,
  hasGpsBackendContract,
  mapTransportErrorCode,
  resolveListState,
  toDefaultCrewPayload,
  validateDefaultCrewForm,
  validateDriverForm,
  validatePointForm,
  validateSeasonVehicleForm,
  validateVehicleForm,
} from './fleetValidation'

function vehicle(overrides: Partial<VehicleFormState> = {}): VehicleFormState {
  return { ...emptyVehicleForm(), ...overrides }
}

function driver(overrides: Partial<DriverFormState> = {}): DriverFormState {
  return { ...emptyDriverForm(), ...overrides }
}

function seasonVehicle(overrides: Partial<SeasonVehicleFormState> = {}): SeasonVehicleFormState {
  return { ...emptySeasonVehicleForm(), ...overrides }
}

describe('validateVehicleForm', () => {
  it('requires plate and class', () => {
    expect(validateVehicleForm(vehicle())).toEqual({
      plate_number: 'required',
      vehicle_class: 'required',
    })
  })

  it('rejects invalid plate format', () => {
    expect(validateVehicleForm(vehicle({ plate_number: 'AB@1', vehicle_class: 'bus' }))).toEqual({
      plate_number: 'format',
    })
  })

  it('accepts a valid vehicle', () => {
    expect(
      validateVehicleForm(vehicle({ plate_number: 'ABC-1234', vehicle_class: 'minibus', notes: 'x' })),
    ).toEqual({})
  })
})

describe('validateDriverForm', () => {
  it('requires full name', () => {
    expect(validateDriverForm(driver())).toEqual({ full_name: 'required' })
  })

  it('accepts a valid driver', () => {
    expect(validateDriverForm(driver({ full_name: 'أحمد', phone: '0500000000' }))).toEqual({})
  })
})

describe('validateSeasonVehicleForm', () => {
  it('requires vehicle, capacity, and service level on create', () => {
    expect(validateSeasonVehicleForm(seasonVehicle(), true)).toEqual({
      transport_vehicle_id: 'required',
      operational_capacity: 'range',
      service_level: 'required',
    })
  })

  it('skips vehicle id when editing', () => {
    expect(
      validateSeasonVehicleForm(
        seasonVehicle({ operational_capacity: '45', service_level: 'standard' }),
        false,
      ),
    ).toEqual({})
  })

  it('rejects out-of-range capacity', () => {
    expect(
      validateSeasonVehicleForm(
        seasonVehicle({
          transport_vehicle_id: 1,
          operational_capacity: '0',
          service_level: 'premium',
        }),
        true,
      ),
    ).toEqual({ operational_capacity: 'range' })
  })

  it('accepts numeric operational_capacity from type=number v-model', () => {
    expect(
      validateSeasonVehicleForm(
        seasonVehicle({
          transport_vehicle_id: 1,
          operational_capacity: 45 as unknown as string,
          service_level: 'standard',
        }),
        true,
      ),
    ).toEqual({})
  })
})

describe('resolveListState', () => {
  it('surfaces need_season before loading', () => {
    expect(resolveListState({ hasSeason: false, isLoading: true, isError: false, count: 0 })).toBe(
      'need_season',
    )
  })

  it('maps loading, error, empty, ready', () => {
    expect(resolveListState({ isLoading: true, isError: false, count: 0 })).toBe('loading')
    expect(resolveListState({ isLoading: false, isError: true, count: 0 })).toBe('error')
    expect(resolveListState({ isLoading: false, isError: false, count: 0 })).toBe('empty')
    expect(resolveListState({ isLoading: false, isError: false, count: 2 })).toBe('ready')
  })
})

describe('permissions helpers', () => {
  it('gates registry and season fleet independently of view', () => {
    expect(canViewTransportation(['transportation.view'])).toBe(true)
    expect(canManageRegistry(['transportation.view'])).toBe(false)
    expect(canManageRegistry(['transportation.manage_registry'])).toBe(true)
    expect(canManageSeasonFleet(['transportation.manage_season_fleet'])).toBe(true)
    expect(canManageSeasonFleet(['transportation.manage_registry'])).toBe(false)
  })
})

describe('mapTransportErrorCode', () => {
  it('maps SEASON_CLOSED and registry domain codes', () => {
    expect(mapTransportErrorCode('SEASON_CLOSED')).toBe('seasonClosed')
    expect(mapTransportErrorCode('TRANSPORT_POINT_CODE_TAKEN')).toBe('pointCodeTaken')
    expect(mapTransportErrorCode('TRANSPORT_POINT_IN_USE')).toBe('pointInUse')
    expect(mapTransportErrorCode('TRANSPORT_VEHICLE_ASSET_TAKEN')).toBe('assetTaken')
    expect(mapTransportErrorCode('TRANSPORT_LINK_INVALID')).toBe('linkInvalid')
    expect(mapTransportErrorCode('TRANSPORT_DRIVER_INVALID')).toBe('driverInvalid')
    expect(mapTransportErrorCode('OTHER')).toBe('generic')
  })
})

describe('validatePointForm', () => {
  function point(overrides: Partial<PointFormState> = {}): PointFormState {
    return { ...emptyPointForm(), ...overrides }
  }

  it('requires code, name, and type', () => {
    expect(validatePointForm(point())).toEqual({
      code: 'required',
      name: 'required',
      point_type: 'required',
    })
  })

  it('rejects invalid codes', () => {
    expect(validatePointForm(point({ code: 'bad code!', name: 'x', point_type: 'airport' }))).toEqual(
      { code: 'format' },
    )
  })

  it('accepts a valid point', () => {
    expect(
      validatePointForm(point({ code: 'JED_1', name: 'مطار جدة', point_type: 'airport' })),
    ).toEqual({})
  })
})

describe('default crew', () => {
  function crew(overrides: Partial<DefaultCrewFormState> = {}): DefaultCrewFormState {
    return { ...emptyDefaultCrewForm(), ...overrides }
  }

  it('requires selected driver when kind is employee/external', () => {
    expect(validateDefaultCrewForm(crew({ driver_kind: 'employee' }))).toEqual({
      driver: 'required',
    })
    expect(validateDefaultCrewForm(crew({ driver_kind: 'external' }))).toEqual({
      driver: 'required',
    })
    expect(validateDefaultCrewForm(crew({ driver_kind: 'none' }))).toEqual({})
  })

  it('builds exclusive driver payload', () => {
    expect(
      toDefaultCrewPayload(
        crew({
          driver_kind: 'employee',
          default_driver_employee_id: 5,
          default_external_driver_id: 9,
          default_supervisor_employee_id: 3,
        }),
      ),
    ).toEqual({
      default_driver_employee_id: 5,
      default_external_driver_id: null,
      default_supervisor_employee_id: 3,
    })
  })
})

describe('GPS readiness', () => {
  it('reports no GPS backend contract on TransportVehicle', () => {
    expect(hasGpsBackendContract()).toBe(false)
  })
})
