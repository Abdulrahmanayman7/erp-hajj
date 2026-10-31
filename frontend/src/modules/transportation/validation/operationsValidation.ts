import type { CreateRunFormState, MovementFormState, MovementStatus, RunStatus } from '../types/operations'

export interface MovementFieldErrors {
  origin_point_id?: string
  destination_point_id?: string
  planned_departure_at?: string
  planned_arrival_at?: string
  notes?: string
}

export interface CreateRunFieldErrors {
  transport_season_vehicle_id?: string
  capacity_override?: string
  notes?: string
}

export function emptyMovementForm(): MovementFormState {
  return {
    origin_point_id: '',
    destination_point_id: '',
    planned_departure_at: '',
    planned_arrival_at: '',
    notes: '',
  }
}

export function emptyCreateRunForm(): CreateRunFormState {
  return {
    transport_season_vehicle_id: '',
    capacity_override: '',
    notes: '',
  }
}

export function validateMovementForm(form: MovementFormState): MovementFieldErrors {
  const errors: MovementFieldErrors = {}
  if (!form.origin_point_id) errors.origin_point_id = 'required'
  if (!form.destination_point_id) errors.destination_point_id = 'required'
  if (
    form.origin_point_id &&
    form.destination_point_id &&
    String(form.origin_point_id) === String(form.destination_point_id)
  ) {
    errors.destination_point_id = 'samePoint'
  }
  if (!form.planned_departure_at.trim()) errors.planned_departure_at = 'required'
  if (!form.planned_arrival_at.trim()) errors.planned_arrival_at = 'required'
  if (
    form.planned_departure_at.trim() &&
    form.planned_arrival_at.trim() &&
    new Date(form.planned_arrival_at).getTime() <= new Date(form.planned_departure_at).getTime()
  ) {
    errors.planned_arrival_at = 'afterDeparture'
  }
  if (form.notes.length > 5000) errors.notes = 'max'
  return errors
}

export function validateCreateRunForm(form: CreateRunFormState): CreateRunFieldErrors {
  const errors: CreateRunFieldErrors = {}
  if (!form.transport_season_vehicle_id) errors.transport_season_vehicle_id = 'required'
  const raw = String(form.capacity_override ?? '').trim()
  if (raw !== '') {
    const n = Number(raw)
    if (!Number.isInteger(n) || n < 1 || n > 1000) errors.capacity_override = 'range'
  }
  if (form.notes.length > 5000) errors.notes = 'max'
  return errors
}

/** Movement transitions exposed by Backend (ADR-0018). */
export function movementActionsFor(status: MovementStatus): Array<'plan' | 'start' | 'complete' | 'cancel'> {
  switch (status) {
    case 'draft':
      return ['plan', 'cancel']
    case 'planned':
      return ['start', 'cancel']
    case 'in_progress':
      return ['complete']
    default:
      return []
  }
}

/** Run transitions exposed by Backend (ADR-0018). */
export function runActionsFor(status: RunStatus): Array<
  'start-boarding' | 'depart' | 'arrive' | 'complete' | 'cancel'
> {
  switch (status) {
    case 'planned':
      return ['start-boarding', 'cancel']
    case 'boarding':
      return ['depart', 'cancel']
    case 'departed':
      return ['arrive']
    case 'arrived':
      return ['complete']
    default:
      return []
  }
}

export function mapOperationsErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SEASON_CLOSED':
      return 'seasonClosed'
    case 'SEASON_NOT_ACTIVE':
      return 'seasonNotActive'
    case 'TRANSPORT_POINTS_SAME':
      return 'pointsSame'
    case 'TRANSPORT_MOVEMENT_INVALID_TRANSITION':
      return 'movementInvalidTransition'
    case 'TRANSPORT_MOVEMENT_NOT_EDITABLE':
      return 'movementNotEditable'
    case 'TRANSPORT_MOVEMENT_RUNS_NOT_SETTLED':
      return 'movementRunsNotSettled'
    case 'TRANSPORT_RUN_INVALID_TRANSITION':
      return 'runInvalidTransition'
    case 'TRANSPORT_RUN_NOT_EDITABLE':
      return 'runNotEditable'
    case 'TRANSPORT_RUN_DRIVER_REQUIRED':
      return 'runDriverRequired'
    case 'TRANSPORT_RUN_HAS_BOARDED_PASSENGERS':
      return 'runHasBoarded'
    case 'TRANSPORT_RUN_PASSENGERS_NOT_SETTLED':
      return 'runPassengersNotSettled'
    case 'TRANSPORT_CAPACITY_EXCEEDED':
    case 'TRANSPORT_RUN_CAPACITY_OVERRIDE_INVALID':
    case 'TRANSPORT_CAPACITY_BELOW_ASSIGNED':
      return 'capacity'
    case 'TRANSPORT_PASSENGER_DUPLICATE':
    case 'TRANSPORT_PASSENGER_ALREADY_ASSIGNED':
      return 'passengerDuplicate'
    case 'TRANSPORT_RESOURCE_OVERLAP':
    case 'TRANSPORT_VEHICLE_OVERLAP':
    case 'TRANSPORT_DRIVER_OVERLAP':
      return 'overlap'
    case 'TRANSPORT_SEASON_VEHICLE_INACTIVE':
      return 'seasonVehicleInactive'
    default:
      return 'generic'
  }
}
