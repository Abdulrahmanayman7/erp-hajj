export type FacilityKind = 'hotel' | 'camp'
export type StructureKind = 'building' | 'floor'
export type UnitKind = 'room' | 'tent'
export type AllocationMode = 'capacity' | 'position'
export type Audience = 'male' | 'female' | 'family'
export type SeasonStatus = 'draft' | 'active' | 'closed'

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface AccommodationSeason {
  id: number
  code: string
  name: string
  hajj_year: number
  status: SeasonStatus
  operational_starts_on: string
  operational_ends_on: string
}

export interface SeasonContextPayload {
  season: AccommodationSeason
  selection: 'explicit' | 'default_active'
  is_writable: boolean
}

export interface AccommodationFacility {
  id: number
  kind: FacilityKind
  code: string
  name: string
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationStructure {
  id: number
  facility_id: number
  parent_id: number | null
  kind: StructureKind
  name: string
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationUnit {
  id: number
  facility_id: number
  structure_id: number | null
  unit_kind: UnitKind
  code: string
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationPosition {
  id: number
  unit_id: number
  code: string
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationSeasonFacility {
  id: number
  season_id: number
  facility_id: number
  audience: Audience | null
  is_active: boolean
  capacity_reserved: boolean
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationSeasonUnit {
  id: number
  season_id: number
  season_facility_id: number
  facility_id: number
  unit_id: number
  allocation_mode: AllocationMode
  capacity: number | null
  audience: Audience
  is_active: boolean
  capacity_reserved: boolean
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationSeasonPosition {
  id: number
  season_id: number
  season_unit_id: number
  unit_id: number
  position_id: number
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

/** Backend booking_status values (Sprint 3). */
export type BookingStatus = 'draft' | 'confirmed' | 'cancelled' | 'completed'

/** Backend operational_status values; null on confirmed rows means assigned. */
export type OperationalStatus = 'assigned' | 'arrived' | 'checked_in' | 'checked_out'

export interface AccommodationAssignment {
  id: number
  season_id: number
  season_pilgrim_id: number
  season_unit_id: number
  unit_id: number
  season_position_id: number | null
  booking_status: BookingStatus
  operational_status: OperationalStatus | null
  starts_at: string
  original_ends_at: string
  effective_ends_at: string
  actual_departed_at: string | null
  checkout_source: string | null
  relocated_from_assignment_id: number | null
  capacity_reserved: boolean
  created_at: string | null
  updated_at: string | null
}

export interface RelocateAssignmentPayload {
  destination_season_unit_id: number
  destination_season_position_id?: number | null
  occurred_at?: string | null
  check_in_after?: boolean
}

export interface RelocateAssignmentResult {
  source: AccommodationAssignment
  destination: AccommodationAssignment
}

export interface OpsOccurredAtPayload {
  occurred_at?: string | null
}

export interface CancelAssignmentPayload {
  reason?: string | null
}

export interface ConfirmFamilyPayload {
  season_family_group_id: number
  assignment_ids: number[]
}

export interface SeasonFamilyGroupLookup {
  id: number
  season_id: number
  name: string
}

export interface AccommodationFacilityScope {
  id: number
  season_id: number
  facility_id: number
  user_id: number
  granted_by: number | null
  revoked_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AccommodationSupervisorScope {
  id: number
  season_id: number
  user_id: number
  granted_by: number | null
  revoked_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface SeasonPilgrimLookup {
  id: number
  season_id: number
  reference_number: string
  status: string
  pilgrim: { id: number; full_name: string } | null
}

export interface FacilityFormState {
  kind: FacilityKind
  code: string
  name: string
  is_active: boolean
}

export interface StructureFormState {
  kind: StructureKind
  name: string
  parent_id: number | ''
}

export interface UnitFormState {
  unit_kind: UnitKind
  code: string
  structure_id: number | ''
}

export interface PositionFormState {
  code: string
}

export interface SeasonFacilityFormState {
  facility_id: number | ''
  audience: Audience | ''
  is_active: boolean
}

export interface SeasonUnitFormState {
  season_facility_id: number | ''
  unit_id: number | ''
  allocation_mode: AllocationMode
  capacity: number | ''
  audience: Audience
  is_active: boolean
}

export interface SeasonPositionFormState {
  season_unit_id: number | ''
  position_id: number | ''
  is_active: boolean
}

export interface AssignmentFormState {
  season_pilgrim_id: number | ''
  season_unit_id: number | ''
  season_position_id: number | ''
  starts_at: string
  original_ends_at: string
}

export interface FacilityScopeFormState {
  user_id: number | ''
  facility_id: number | ''
}

export interface SupervisorScopeFormState {
  user_id: number | ''
}

export const ACCOMMODATION_READ_PERMISSIONS = [
  'accommodation.view',
  'accommodation.draft',
] as const

export const ACCOMMODATION_OPS_PERMISSIONS = [
  'accommodation.arrive',
  'accommodation.check_in',
  'accommodation.check_out',
  'accommodation.relocate',
  'accommodation.cancel_assignment',
] as const

export const ACCOMMODATION_ANY_PERMISSIONS = [
  'accommodation.view',
  'accommodation.manage_facilities',
  'accommodation.manage_season_allocation',
  'accommodation.draft',
  'accommodation.confirm',
  'accommodation.manage_scopes',
  ...ACCOMMODATION_OPS_PERMISSIONS,
] as const
