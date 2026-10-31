export interface BreakdownFormState {
  reason: string
  notes: string
}

export interface BreakdownFieldErrors {
  reason?: string
  notes?: string
}

export interface ReplacementFormState {
  replacement_season_vehicle_id: string | number | ''
  reason: string
}

export interface ReplacementFieldErrors {
  replacement_season_vehicle_id?: string
  reason?: string
  plan?: string
}

export function emptyBreakdownForm(): BreakdownFormState {
  return { reason: '', notes: '' }
}

export function emptyReplacementForm(): ReplacementFormState {
  return { replacement_season_vehicle_id: '', reason: '' }
}

export function validateBreakdownForm(form: BreakdownFormState): BreakdownFieldErrors {
  const errors: BreakdownFieldErrors = {}
  if (form.reason.trim().length < 3) errors.reason = 'min'
  if (form.notes.length > 5000) errors.notes = 'max'
  return errors
}

export function validateReplacementForm(
  form: ReplacementFormState,
  options: { planRequired: boolean; planCount: number; capacityGap: number; requireReason: boolean },
): ReplacementFieldErrors {
  const errors: ReplacementFieldErrors = {}
  if (!form.replacement_season_vehicle_id) errors.replacement_season_vehicle_id = 'required'
  if (options.requireReason && form.reason.trim().length < 3) errors.reason = 'min'
  if (options.planRequired && options.planCount < options.capacityGap) errors.plan = 'insufficient'
  return errors
}

export function mapBreakdownErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SEASON_CLOSED':
      return 'seasonClosed'
    case 'TRANSPORT_BREAKDOWN_NOT_OPEN':
      return 'notOpen'
    case 'TRANSPORT_BREAKDOWN_VEHICLE_MISMATCH':
      return 'vehicleMismatch'
    case 'TRANSPORT_BREAKDOWN_RUN_TERMINAL':
      return 'runTerminal'
    case 'TRANSPORT_REPLACEMENT_CAPACITY_PLAN_REQUIRED':
      return 'planRequired'
    case 'TRANSPORT_REPLACEMENT_PLAN_INVALID':
      return 'planInvalid'
    case 'TRANSPORT_REPLACEMENT_BOARDED_EXCEED_CAPACITY':
      return 'boardedBlock'
    case 'TRANSPORT_REPLACEMENT_TARGET_RUN_INVALID':
      return 'targetInvalid'
    case 'TRANSPORT_REPLACEMENT_SAME_VEHICLE':
      return 'sameVehicle'
    case 'TRANSPORT_VEHICLE_OVERLAP':
      return 'overlap'
    case 'TRANSPORT_CAPACITY_EXCEEDED':
    case 'TRANSPORT_CAPACITY_BELOW_ASSIGNED':
      return 'capacity'
    default:
      return 'generic'
  }
}
