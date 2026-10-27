import type {
  AssignmentFormState,
  BookingStatus,
  ConfirmFamilyPayload,
  FacilityFormState,
  FacilityScopeFormState,
  OperationalStatus,
  PositionFormState,
  RelocateAssignmentPayload,
  SeasonFacilityFormState,
  SeasonPositionFormState,
  SeasonStatus,
  SeasonUnitFormState,
  StructureFormState,
  SupervisorScopeFormState,
  UnitFormState,
} from '../types/accommodation'

export type ListState = 'loading' | 'error' | 'empty' | 'ready'

export function resolveListState(input: {
  isLoading: boolean
  isError: boolean
  count: number
}): ListState {
  if (input.isLoading) return 'loading'
  if (input.isError) return 'error'
  if (input.count === 0) return 'empty'
  return 'ready'
}

export function mapAccommodationErrorCode(code: string | undefined): string {
  switch (code) {
    case 'SEASON_CLOSED':
      return 'seasonClosed'
    case 'SEASON_NOT_ACTIVE':
      return 'seasonNotActive'
    case 'SEASON_CONTEXT_MISSING':
      return 'seasonMissing'
    case 'FORBIDDEN':
    case 'AUTHORIZATION_DENIED':
      return 'forbidden'
    case 'ACCOMMODATION_CODE_TAKEN':
      return 'codeTaken'
    case 'ACCOMMODATION_STRUCTURE_CYCLE':
      return 'structureCycle'
    case 'ACCOMMODATION_INVALID_REFERENCE':
      return 'invalidReference'
    case 'ACCOMMODATION_REFERENCED':
      return 'referenced'
    case 'ACCOMMODATION_INTERVAL_INVALID':
      return 'intervalInvalid'
    case 'ACCOMMODATION_GRANTEE_TENANT':
      return 'granteeTenant'
    case 'ACCOMMODATION_SCOPE_EXISTS':
      return 'scopeExists'
    case 'ACCOMMODATION_SCOPE_REVOKED':
      return 'scopeRevoked'
    case 'ACCOMMODATION_PILGRIM_OVERLAP':
      return 'pilgrimOverlap'
    case 'ACCOMMODATION_CAPACITY_CONFLICT':
      return 'capacityConflict'
    case 'ACCOMMODATION_FAMILY_EXCLUSIVITY':
      return 'familyExclusivity'
    case 'ACCOMMODATION_ILLEGAL_TRANSITION':
      return 'illegalTransition'
    case 'ACCOMMODATION_CHECKOUT_BEFORE_START':
      return 'checkoutBeforeStart'
    case 'ACCOMMODATION_RELOCATE_INTERVAL_INVALID':
      return 'relocateIntervalInvalid'
    case 'VALIDATION_ERROR':
      return 'validation'
    default:
      return 'generic'
  }
}

/** Treat null operational_status on confirmed rows as assigned (backend contract). */
export function effectiveOperationalStatus(
  status: OperationalStatus | null | undefined,
): OperationalStatus {
  return status ?? 'assigned'
}

function isActiveSeason(seasonStatus: SeasonStatus | null): boolean {
  return seasonStatus === 'active'
}

/** UI visibility only — backend re-checks permission, scope, and Active season. */
export function canShowConfirmAction(input: {
  hasConfirmPermission: boolean
  seasonStatus: SeasonStatus | null
  bookingStatus: BookingStatus
}): boolean {
  return (
    input.hasConfirmPermission &&
    isActiveSeason(input.seasonStatus) &&
    input.bookingStatus === 'draft'
  )
}

export function canShowArriveAction(input: {
  hasPermission: boolean
  seasonStatus: SeasonStatus | null
  bookingStatus: BookingStatus
  operationalStatus: OperationalStatus | null
}): boolean {
  if (!input.hasPermission || !isActiveSeason(input.seasonStatus)) return false
  if (input.bookingStatus !== 'confirmed') return false
  return effectiveOperationalStatus(input.operationalStatus) === 'assigned'
}

export function canShowCheckInAction(input: {
  hasPermission: boolean
  seasonStatus: SeasonStatus | null
  bookingStatus: BookingStatus
  operationalStatus: OperationalStatus | null
}): boolean {
  if (!input.hasPermission || !isActiveSeason(input.seasonStatus)) return false
  if (input.bookingStatus !== 'confirmed') return false
  const ops = effectiveOperationalStatus(input.operationalStatus)
  return ops === 'assigned' || ops === 'arrived'
}

export function canShowCheckOutAction(input: {
  hasPermission: boolean
  seasonStatus: SeasonStatus | null
  bookingStatus: BookingStatus
  operationalStatus: OperationalStatus | null
}): boolean {
  if (!input.hasPermission || !isActiveSeason(input.seasonStatus)) return false
  if (input.bookingStatus !== 'confirmed') return false
  return effectiveOperationalStatus(input.operationalStatus) === 'checked_in'
}

export function canShowCancelAssignmentAction(input: {
  hasPermission: boolean
  seasonStatus: SeasonStatus | null
  bookingStatus: BookingStatus
  operationalStatus: OperationalStatus | null
}): boolean {
  if (!input.hasPermission || !isActiveSeason(input.seasonStatus)) return false
  if (input.bookingStatus !== 'confirmed') return false
  const ops = effectiveOperationalStatus(input.operationalStatus)
  return ops === 'assigned' || ops === 'arrived'
}

export function canShowRelocateAction(input: {
  hasPermission: boolean
  seasonStatus: SeasonStatus | null
  bookingStatus: BookingStatus
}): boolean {
  return (
    input.hasPermission &&
    isActiveSeason(input.seasonStatus) &&
    input.bookingStatus === 'confirmed'
  )
}

export function buildRelocatePayload(input: {
  destinationSeasonUnitId: number
  destinationSeasonPositionId?: number | ''
  occurredAtLocal?: string
  checkInAfter?: boolean
}): RelocateAssignmentPayload {
  const payload: RelocateAssignmentPayload = {
    destination_season_unit_id: Number(input.destinationSeasonUnitId),
  }
  if (input.destinationSeasonPositionId !== undefined && input.destinationSeasonPositionId !== '') {
    payload.destination_season_position_id = Number(input.destinationSeasonPositionId)
  }
  if (input.occurredAtLocal?.trim()) {
    payload.occurred_at = fromLocalDatetimeInput(input.occurredAtLocal)
  }
  if (input.checkInAfter) {
    payload.check_in_after = true
  }
  return payload
}

/** Confirmed live reservation vs completed historical interval (UI label only). */
export function capacityDisplayKind(input: {
  bookingStatus: BookingStatus
  capacityReserved: boolean
}): 'reserved' | 'historical' | 'none' {
  if (input.bookingStatus === 'confirmed' && input.capacityReserved) return 'reserved'
  if (input.bookingStatus === 'completed') return 'historical'
  return 'none'
}

export function buildConfirmFamilyPayload(
  seasonFamilyGroupId: number,
  assignmentIds: number[],
): ConfirmFamilyPayload {
  const ids = [...new Set(assignmentIds.map((id) => Number(id)).filter((id) => id > 0))].sort(
    (a, b) => a - b,
  )
  return {
    season_family_group_id: Number(seasonFamilyGroupId),
    assignment_ids: ids,
  }
}

export function validateFacilityForm(form: FacilityFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.kind) errors.kind = 'required'
  if (!form.name.trim()) errors.name = 'required'
  return errors
}

export function validateStructureForm(form: StructureFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.kind) errors.kind = 'required'
  if (!form.name.trim()) errors.name = 'required'
  return errors
}

export function validateUnitForm(form: UnitFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (!form.unit_kind) errors.unit_kind = 'required'
  return errors
}

export function validatePositionForm(_form: PositionFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  return errors
}

export function validateSeasonFacilityForm(form: SeasonFacilityFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.facility_id === '') errors.facility_id = 'required'
  return errors
}

export function validateSeasonUnitForm(form: SeasonUnitFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.season_facility_id === '') errors.season_facility_id = 'required'
  if (form.unit_id === '') errors.unit_id = 'required'
  if (!form.allocation_mode) errors.allocation_mode = 'required'
  if (!form.audience) errors.audience = 'required'
  if (form.capacity !== '' && Number(form.capacity) < 0) errors.capacity = 'min'
  return errors
}

export function validateSeasonPositionForm(form: SeasonPositionFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.season_unit_id === '') errors.season_unit_id = 'required'
  if (form.position_id === '') errors.position_id = 'required'
  return errors
}

export function validateAssignmentForm(form: AssignmentFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.season_pilgrim_id === '') errors.season_pilgrim_id = 'required'
  if (form.season_unit_id === '') errors.season_unit_id = 'required'
  if (!form.starts_at.trim()) errors.starts_at = 'required'
  if (!form.original_ends_at.trim()) errors.original_ends_at = 'required'
  if (form.starts_at && form.original_ends_at) {
    const start = Date.parse(form.starts_at)
    const end = Date.parse(form.original_ends_at)
    if (!Number.isNaN(start) && !Number.isNaN(end) && end <= start) {
      errors.original_ends_at = 'interval'
    }
  }
  return errors
}

export function validateFacilityScopeForm(form: FacilityScopeFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.user_id === '') errors.user_id = 'required'
  if (form.facility_id === '') errors.facility_id = 'required'
  return errors
}

export function validateSupervisorScopeForm(form: SupervisorScopeFormState): Record<string, string> {
  const errors: Record<string, string> = {}
  if (form.user_id === '') errors.user_id = 'required'
  return errors
}

export function emptyFacilityForm(): FacilityFormState {
  return { kind: 'hotel', code: '', name: '', is_active: true }
}

export function emptyStructureForm(): StructureFormState {
  return { kind: 'building', name: '', parent_id: '' }
}

export function emptyUnitForm(): UnitFormState {
  return { unit_kind: 'room', code: '', structure_id: '' }
}

export function emptyPositionForm(): PositionFormState {
  return { code: '' }
}

export function emptyAssignmentForm(): AssignmentFormState {
  return {
    season_pilgrim_id: '',
    season_unit_id: '',
    season_position_id: '',
    starts_at: '',
    original_ends_at: '',
  }
}

export function filterFacilitiesClientSide<T extends { code: string; name: string; kind: string; is_active: boolean }>(
  rows: T[],
  options: { search: string; kind: string; active: '' | '1' | '0' },
): T[] {
  const q = options.search.trim().toLowerCase()
  return rows.filter((row) => {
    if (options.kind && row.kind !== options.kind) return false
    if (options.active === '1' && !row.is_active) return false
    if (options.active === '0' && row.is_active) return false
    if (!q) return true
    return row.code.toLowerCase().includes(q) || row.name.toLowerCase().includes(q)
  })
}

export function toUtcInputValue(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso.slice(0, 16)
  return d.toISOString().slice(0, 16)
}

/** datetime-local must show the browser's local wall time before converting back to UTC. */
export function toLocalDatetimeInput(iso: string): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (value: number) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export function fromLocalDatetimeInput(value: string): string {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value
  return d.toISOString()
}
