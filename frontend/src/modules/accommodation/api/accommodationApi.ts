import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type {
  AccommodationAssignment,
  AccommodationFacility,
  AccommodationFacilityScope,
  AccommodationPosition,
  AccommodationSeasonFacility,
  AccommodationSeasonPosition,
  AccommodationSeasonUnit,
  AccommodationStructure,
  AccommodationSupervisorScope,
  AccommodationUnit,
  CancelAssignmentPayload,
  OpsOccurredAtPayload,
  PaginationMeta,
  RelocateAssignmentPayload,
  RelocateAssignmentResult,
} from '../types/accommodation'

function opts() {
  return { headers: useAccommodationSeasonStore().seasonHeaders() }
}

function pageQuery(page = 1, perPage = 15): string {
  return `?page=${page}&per_page=${perPage}`
}

function listMeta(meta: unknown): PaginationMeta {
  return meta as PaginationMeta
}

export async function listFacilities(page = 1, perPage = 15) {
  const response = await apiGet<AccommodationFacility[]>(
    `/api/v1/accommodation/facilities${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function getFacility(id: number) {
  const response = await apiGet<AccommodationFacility>(
    `/api/v1/accommodation/facilities/${id}`,
    opts(),
  )
  return response.data
}

export async function createFacility(payload: {
  kind: string
  code?: string
  name: string
}) {
  const response = await apiPost<AccommodationFacility>(
    '/api/v1/accommodation/facilities',
    payload,
    opts(),
  )
  return response.data
}

export async function updateFacility(
  id: number,
  payload: Partial<{ kind: string; code?: string; name: string; is_active: boolean }>,
) {
  const response = await apiPatch<AccommodationFacility>(
    `/api/v1/accommodation/facilities/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteFacility(id: number) {
  await apiDelete(`/api/v1/accommodation/facilities/${id}`, opts())
}

export async function listStructures(facilityId: number, page = 1, perPage = 50) {
  const response = await apiGet<AccommodationStructure[]>(
    `/api/v1/accommodation/facilities/${facilityId}/structures${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createStructure(
  facilityId: number,
  payload: { kind: string; name: string; parent_id?: number | null },
) {
  const response = await apiPost<AccommodationStructure>(
    `/api/v1/accommodation/facilities/${facilityId}/structures`,
    payload,
    opts(),
  )
  return response.data
}

export async function updateStructure(
  id: number,
  payload: Partial<{ kind: string; name: string; parent_id: number | null }>,
) {
  const response = await apiPatch<AccommodationStructure>(
    `/api/v1/accommodation/structures/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteStructure(id: number) {
  await apiDelete(`/api/v1/accommodation/structures/${id}`, opts())
}

export async function listUnits(facilityId: number, page = 1, perPage = 50) {
  const response = await apiGet<AccommodationUnit[]>(
    `/api/v1/accommodation/facilities/${facilityId}/units${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createUnit(
  facilityId: number,
  payload: { unit_kind: string; code?: string; structure_id?: number | null },
) {
  const response = await apiPost<AccommodationUnit>(
    `/api/v1/accommodation/facilities/${facilityId}/units`,
    payload,
    opts(),
  )
  return response.data
}

export async function updateUnit(
  id: number,
  payload: Partial<{ unit_kind: string; code?: string; structure_id: number | null }>,
) {
  const response = await apiPatch<AccommodationUnit>(
    `/api/v1/accommodation/units/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteUnit(id: number) {
  await apiDelete(`/api/v1/accommodation/units/${id}`, opts())
}

export async function listPositions(unitId: number, page = 1, perPage = 50, search = '', signal?: AbortSignal) {
  const response = await apiGet<AccommodationPosition[]>(
    `/api/v1/accommodation/units/${unitId}/positions${pageQuery(page, perPage)}${search ? '&search=' + encodeURIComponent(search) : ''}`,
    { ...opts(), signal },
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createPosition(unitId: number, payload: { code?: string }) {
  const response = await apiPost<AccommodationPosition>(
    `/api/v1/accommodation/units/${unitId}/positions`,
    payload,
    opts(),
  )
  return response.data
}

export async function updatePosition(id: number, payload: { code?: string }) {
  const response = await apiPatch<AccommodationPosition>(
    `/api/v1/accommodation/positions/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deletePosition(id: number) {
  await apiDelete(`/api/v1/accommodation/positions/${id}`, opts())
}

export async function listSeasonFacilities(page = 1, perPage = 15) {
  const response = await apiGet<AccommodationSeasonFacility[]>(
    `/api/v1/accommodation/season-facilities${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createSeasonFacility(payload: {
  facility_id: number
  audience?: string | null
  is_active?: boolean
}) {
  const response = await apiPost<AccommodationSeasonFacility>(
    '/api/v1/accommodation/season-facilities',
    payload,
    opts(),
  )
  return response.data
}

export async function updateSeasonFacility(
  id: number,
  payload: Partial<{ audience: string | null; is_active: boolean }>,
) {
  const response = await apiPatch<AccommodationSeasonFacility>(
    `/api/v1/accommodation/season-facilities/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteSeasonFacility(id: number) {
  await apiDelete(`/api/v1/accommodation/season-facilities/${id}`, opts())
}

export async function listSeasonUnits(page = 1, perPage = 15) {
  const response = await apiGet<AccommodationSeasonUnit[]>(
    `/api/v1/accommodation/season-units${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createSeasonUnit(payload: {
  season_facility_id: number
  unit_id: number
  allocation_mode: string
  capacity?: number | null
  audience: string
  is_active?: boolean
}) {
  const response = await apiPost<AccommodationSeasonUnit>(
    '/api/v1/accommodation/season-units',
    payload,
    opts(),
  )
  return response.data
}

export async function updateSeasonUnit(
  id: number,
  payload: Partial<{
    allocation_mode: string
    capacity: number | null
    audience: string
    is_active: boolean
  }>,
) {
  const response = await apiPatch<AccommodationSeasonUnit>(
    `/api/v1/accommodation/season-units/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteSeasonUnit(id: number) {
  await apiDelete(`/api/v1/accommodation/season-units/${id}`, opts())
}

export async function listSeasonPositions(page = 1, perPage = 15) {
  const response = await apiGet<AccommodationSeasonPosition[]>(
    `/api/v1/accommodation/season-positions${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createSeasonPosition(payload: {
  season_unit_id: number
  position_id: number
  is_active?: boolean
}) {
  const response = await apiPost<AccommodationSeasonPosition>(
    '/api/v1/accommodation/season-positions',
    payload,
    opts(),
  )
  return response.data
}

export async function updateSeasonPosition(id: number, payload: Partial<{ is_active: boolean }>) {
  const response = await apiPatch<AccommodationSeasonPosition>(
    `/api/v1/accommodation/season-positions/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteSeasonPosition(id: number) {
  await apiDelete(`/api/v1/accommodation/season-positions/${id}`, opts())
}

export interface AssignmentListFilters { search?: string; booking_status?: string; facility_id?: number; season_unit_id?: number }

export async function listAssignments(page = 1, perPage = 15, filters: AssignmentListFilters = {}, signal?: AbortSignal) {
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(filters)) if (value !== undefined && value !== '') query.set(key, String(value))
  const suffix = query.size ? '&' + query.toString() : ''
  const response = await apiGet<AccommodationAssignment[]>(
    `/api/v1/accommodation/assignments${pageQuery(page, perPage)}${suffix}`,
    { ...opts(), signal },
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function createAssignment(payload: {
  season_pilgrim_id: number
  season_unit_id: number
  season_position_id?: number | null
  starts_at: string
  original_ends_at: string
}) {
  const response = await apiPost<AccommodationAssignment>(
    '/api/v1/accommodation/assignments',
    payload,
    opts(),
  )
  return response.data
}

export async function updateAssignment(
  id: number,
  payload: Partial<{
    season_unit_id: number
    season_position_id: number | null
    starts_at: string
    original_ends_at: string
  }>,
) {
  const response = await apiPatch<AccommodationAssignment>(
    `/api/v1/accommodation/assignments/${id}`,
    payload,
    opts(),
  )
  return response.data
}

export async function deleteAssignment(id: number) {
  await apiDelete(`/api/v1/accommodation/assignments/${id}`, opts())
}

export async function confirmAssignment(id: number) {
  const response = await apiPost<AccommodationAssignment>(
    `/api/v1/accommodation/assignments/${id}/confirm`,
    {},
    opts(),
  )
  return response.data
}

export async function confirmFamilyAssignments(payload: {
  season_family_group_id: number
  assignment_ids: number[]
}) {
  const response = await apiPost<AccommodationAssignment[]>(
    '/api/v1/accommodation/assignments/confirm-family',
    {
      season_family_group_id: payload.season_family_group_id,
      assignment_ids: payload.assignment_ids,
    },
    opts(),
  )
  return response.data
}

function opsBody(payload?: OpsOccurredAtPayload): Record<string, string> {
  if (payload?.occurred_at) {
    return { occurred_at: payload.occurred_at }
  }
  return {}
}

export async function arriveAssignment(id: number, payload?: OpsOccurredAtPayload) {
  const response = await apiPost<AccommodationAssignment>(
    `/api/v1/accommodation/assignments/${id}/arrive`,
    opsBody(payload),
    opts(),
  )
  return response.data
}

export async function checkInAssignment(id: number, payload?: OpsOccurredAtPayload) {
  const response = await apiPost<AccommodationAssignment>(
    `/api/v1/accommodation/assignments/${id}/check-in`,
    opsBody(payload),
    opts(),
  )
  return response.data
}

export async function checkOutAssignment(id: number, payload?: OpsOccurredAtPayload) {
  const response = await apiPost<AccommodationAssignment>(
    `/api/v1/accommodation/assignments/${id}/check-out`,
    opsBody(payload),
    opts(),
  )
  return response.data
}

export async function cancelConfirmedAssignment(id: number, payload?: CancelAssignmentPayload) {
  const body: Record<string, string> = {}
  if (payload?.reason) {
    body.reason = payload.reason
  }
  const response = await apiPost<AccommodationAssignment>(
    `/api/v1/accommodation/assignments/${id}/cancel`,
    body,
    opts(),
  )
  return response.data
}

export async function relocateAssignment(id: number, payload: RelocateAssignmentPayload) {
  const body: Record<string, unknown> = {
    destination_season_unit_id: payload.destination_season_unit_id,
  }
  if (payload.destination_season_position_id != null) {
    body.destination_season_position_id = payload.destination_season_position_id
  }
  if (payload.occurred_at) {
    body.occurred_at = payload.occurred_at
  }
  if (payload.check_in_after) {
    body.check_in_after = true
  }
  const response = await apiPost<RelocateAssignmentResult>(
    `/api/v1/accommodation/assignments/${id}/relocate`,
    body,
    opts(),
  )
  return response.data
}

export async function listFacilityScopes(page = 1, perPage = 15) {
  const response = await apiGet<AccommodationFacilityScope[]>(
    `/api/v1/accommodation/facility-scopes${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function grantFacilityScope(payload: { user_id: number; facility_id: number }) {
  const response = await apiPost<AccommodationFacilityScope>(
    '/api/v1/accommodation/facility-scopes',
    payload,
    opts(),
  )
  return response.data
}

export async function revokeFacilityScope(id: number) {
  const response = await apiPost<AccommodationFacilityScope>(
    `/api/v1/accommodation/facility-scopes/${id}/revoke`,
    {},
    opts(),
  )
  return response.data
}

export async function listSupervisorScopes(page = 1, perPage = 15) {
  const response = await apiGet<AccommodationSupervisorScope[]>(
    `/api/v1/accommodation/supervisor-scopes${pageQuery(page, perPage)}`,
    opts(),
  )
  return { data: response.data, meta: listMeta(response.meta) }
}

export async function grantSupervisorScope(payload: { user_id: number }) {
  const response = await apiPost<AccommodationSupervisorScope>(
    '/api/v1/accommodation/supervisor-scopes',
    payload,
    opts(),
  )
  return response.data
}

export async function revokeSupervisorScope(id: number) {
  const response = await apiPost<AccommodationSupervisorScope>(
    `/api/v1/accommodation/supervisor-scopes/${id}/revoke`,
    {},
    opts(),
  )
  return response.data
}
