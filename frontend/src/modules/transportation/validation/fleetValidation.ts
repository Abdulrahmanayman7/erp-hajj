import type {
  DefaultCrewFormState,
  DriverFormState,
  ListViewState,
  SeasonVehicleFormState,
  TransportSeasonVehicle,
  VehicleClass,
  VehicleFormState,
  VehicleLinksFormState,
} from '../types/fleet'
import { VEHICLE_CLASSES } from '../types/fleet'
import type { PointFormState, TransportPointType } from '../types/points'
import { POINT_TYPES } from '../types/points'

const PLATE_RE = /^[\p{L}\p{N} \-]+$/u
const POINT_CODE_RE = /^[A-Za-z0-9_\-]+$/

export interface VehicleFieldErrors {
  plate_number?: string
  vehicle_class?: string
  notes?: string
}

export interface DriverFieldErrors {
  full_name?: string
  phone?: string
  notes?: string
}

export interface SeasonVehicleFieldErrors {
  transport_vehicle_id?: string
  operational_capacity?: string
  service_level?: string
  notes?: string
}

export interface PointFieldErrors {
  code?: string
  name?: string
  point_type?: string
  notes?: string
}

export interface DefaultCrewFieldErrors {
  driver?: string
}

export function emptyVehicleForm(): VehicleFormState {
  return { plate_number: '', vehicle_class: '', notes: '' }
}

export function emptyDriverForm(): DriverFormState {
  return { full_name: '', phone: '', notes: '' }
}

export function emptySeasonVehicleForm(): SeasonVehicleFormState {
  return {
    transport_vehicle_id: '',
    operational_capacity: '',
    service_level: '',
    notes: '',
  }
}

export function emptyPointForm(): PointFormState {
  return { code: '', name: '', point_type: '', notes: '' }
}

export function emptyVehicleLinksForm(): VehicleLinksFormState {
  return { asset_id: '', contract_id: '' }
}

export function emptyDefaultCrewForm(): DefaultCrewFormState {
  return {
    driver_kind: 'none',
    default_driver_employee_id: '',
    default_external_driver_id: '',
    default_supervisor_employee_id: '',
  }
}

export function defaultCrewFormFromRow(row: TransportSeasonVehicle): DefaultCrewFormState {
  if (row.default_driver_employee) {
    return {
      driver_kind: 'employee',
      default_driver_employee_id: row.default_driver_employee.id,
      default_external_driver_id: '',
      default_supervisor_employee_id: row.default_supervisor_employee?.id ?? '',
    }
  }
  if (row.default_external_driver) {
    return {
      driver_kind: 'external',
      default_driver_employee_id: '',
      default_external_driver_id: row.default_external_driver.id,
      default_supervisor_employee_id: row.default_supervisor_employee?.id ?? '',
    }
  }
  return {
    driver_kind: 'none',
    default_driver_employee_id: '',
    default_external_driver_id: '',
    default_supervisor_employee_id: row.default_supervisor_employee?.id ?? '',
  }
}

export function toDefaultCrewPayload(form: DefaultCrewFormState): {
  default_driver_employee_id: number | null
  default_external_driver_id: number | null
  default_supervisor_employee_id: number | null
} {
  return {
    default_driver_employee_id:
      form.driver_kind === 'employee' && form.default_driver_employee_id !== ''
        ? Number(form.default_driver_employee_id)
        : null,
    default_external_driver_id:
      form.driver_kind === 'external' && form.default_external_driver_id !== ''
        ? Number(form.default_external_driver_id)
        : null,
    default_supervisor_employee_id:
      form.default_supervisor_employee_id === ''
        ? null
        : Number(form.default_supervisor_employee_id),
  }
}

export function validatePointForm(form: PointFormState): PointFieldErrors {
  const errors: PointFieldErrors = {}
  const code = form.code.trim()
  if (!code) errors.code = 'required'
  else if (code.length > 40 || !POINT_CODE_RE.test(code)) errors.code = 'format'
  if (!form.name.trim()) errors.name = 'required'
  else if (form.name.trim().length > 255) errors.name = 'max'
  if (!form.point_type || !POINT_TYPES.includes(form.point_type as TransportPointType)) {
    errors.point_type = 'required'
  }
  if (form.notes.trim().length > 5000) errors.notes = 'max'
  return errors
}

export function validateDefaultCrewForm(form: DefaultCrewFormState): DefaultCrewFieldErrors {
  const errors: DefaultCrewFieldErrors = {}
  if (form.driver_kind === 'employee' && form.default_driver_employee_id === '') {
    errors.driver = 'required'
  }
  if (form.driver_kind === 'external' && form.default_external_driver_id === '') {
    errors.driver = 'required'
  }
  return errors
}

/** Backend has no GPS provider/location fields on TransportVehicle (ADR-0018 / DATA_MODEL). */
export function hasGpsBackendContract(): boolean {
  return false
}

export function validateVehicleForm(form: VehicleFormState): VehicleFieldErrors {
  const errors: VehicleFieldErrors = {}
  const plate = form.plate_number.trim()
  if (!plate) errors.plate_number = 'required'
  else if (plate.length > 32 || !PLATE_RE.test(plate)) errors.plate_number = 'format'

  if (!form.vehicle_class || !VEHICLE_CLASSES.includes(form.vehicle_class as VehicleClass)) {
    errors.vehicle_class = 'required'
  }
  if (form.notes.trim().length > 5000) errors.notes = 'max'
  return errors
}

export function validateDriverForm(form: DriverFormState): DriverFieldErrors {
  const errors: DriverFieldErrors = {}
  if (!form.full_name.trim()) errors.full_name = 'required'
  else if (form.full_name.trim().length > 150) errors.full_name = 'max'
  if (form.phone.trim().length > 30) errors.phone = 'max'
  if (form.notes.trim().length > 5000) errors.notes = 'max'
  return errors
}

export function validateSeasonVehicleForm(
  form: SeasonVehicleFormState,
  requireVehicle: boolean,
): SeasonVehicleFieldErrors {
  const errors: SeasonVehicleFieldErrors = {}
  if (requireVehicle && (form.transport_vehicle_id === '' || Number(form.transport_vehicle_id) <= 0)) {
    errors.transport_vehicle_id = 'required'
  }
  // type="number" v-model may yield a number; coerce before string checks.
  const capacityRaw = String(form.operational_capacity ?? '').trim()
  const capacity = Number(capacityRaw)
  if (!capacityRaw || !Number.isInteger(capacity) || capacity < 1 || capacity > 1000) {
    errors.operational_capacity = 'range'
  }
  if (!form.service_level) errors.service_level = 'required'
  if (form.notes.trim().length > 5000) errors.notes = 'max'
  return errors
}

export function resolveListState(options: {
  isLoading: boolean
  isError: boolean
  count: number
  hasSeason?: boolean
}): ListViewState {
  if (options.hasSeason === false) return 'need_season'
  if (options.isLoading) return 'loading'
  if (options.isError) return 'error'
  if (options.count === 0) return 'empty'
  return 'ready'
}

export function canManageRegistry(permissions: string[]): boolean {
  return permissions.includes('transportation.manage_registry')
}

export function canManageSeasonFleet(permissions: string[]): boolean {
  return permissions.includes('transportation.manage_season_fleet')
}

export function canViewTransportation(permissions: string[]): boolean {
  return permissions.includes('transportation.view')
}

export function mapTransportErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SEASON_CLOSED':
      return 'seasonClosed'
    case 'TRANSPORT_POINT_CODE_TAKEN':
      return 'pointCodeTaken'
    case 'TRANSPORT_POINT_IN_USE':
      return 'pointInUse'
    case 'TRANSPORT_VEHICLE_ASSET_TAKEN':
      return 'assetTaken'
    case 'TRANSPORT_LINK_INVALID':
      return 'linkInvalid'
    case 'TRANSPORT_DRIVER_INVALID':
      return 'driverInvalid'
    case 'TRANSPORT_EMPLOYEE_INVALID':
      return 'employeeInvalid'
    case 'TRANSPORT_EXTERNAL_DRIVER_INACTIVE':
      return 'externalDriverInactive'
    default:
      return 'generic'
  }
}
