import { apiGet, apiPost } from '@/shared/api/http'

import type { PaginationMeta } from '../types/fleet'
import type { TransportVehicleRun } from '../types/operations'
import { seasonRequestOptions } from './seasonRequest'

export type BreakdownStatus = 'open' | 'replaced' | 'closed' | 'cancelled'

export interface TransportVehicleBreakdown {
  id: number
  season_id: number
  vehicle_run_id: number
  run?: {
    id: number
    run_number: number | string
    status: string
    transport_movement_id: number
  } | null
  transport_season_vehicle_id: number
  vehicle?: {
    plate_number: string | null
    vehicle_number: string | null
    vehicle_class: string | null
    operational_capacity: number | null
  } | null
  status: BreakdownStatus
  occurred_at: string | null
  reason: string
  notes: string | null
  reported_by: number
  replacement_id: number | null
  replacement?: TransportVehicleReplacement | null
  closed_at: string | null
  closed_by: number | null
  closure_reason: string | null
  created_at: string | null
  updated_at: string | null
}

export interface TransportVehicleReplacement {
  id: number
  season_id: number
  breakdown_id: number
  vehicle_run_id: number
  from_season_vehicle_id: number
  to_season_vehicle_id: number
  status: string
  reason: string | null
  passengers_retained: number
  passengers_reassigned: number
  idempotency_key: string | null
  executed_at: string | null
  executed_by: number
  passenger_moves?: Array<{
    id: number
    from_run_id: number
    to_run_id: number
    season_pilgrim_id: number
    source_passenger_id: number
    target_passenger_id: number
  }>
}

export interface ReplacementPreview {
  breakdown_id: number
  vehicle_run_id: number
  occupied_count: number
  boarded_or_arrived_count: number
  assigned_movable: Array<{ id: number; season_pilgrim_id: number; status: string }>
  replacement: {
    transport_season_vehicle_id: number
    operational_capacity: number
    effective_capacity: number
    plate_number: string | null
    overlap_free: boolean
  }
  capacity_gap: number
  plan_required: boolean
  boarded_block: boolean
  eligible_target_runs: Array<{
    id: number
    run_number: number | string
    status: string
    movement_number: string | null
    available_seats: number
    plate_number: string | null
  }>
}

export async function listBreakdowns(
  params: { status?: BreakdownStatus | ''; vehicle_run_id?: number; page?: number; per_page?: number } = {},
  seasonId?: number | null,
): Promise<{ data: TransportVehicleBreakdown[]; meta: PaginationMeta }> {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const qs = query.toString()
  const response = await apiGet<TransportVehicleBreakdown[]>(
    `/api/v1/transport-breakdowns${qs ? `?${qs}` : ''}`,
    seasonRequestOptions(seasonId),
  )
  return { data: response.data, meta: response.meta as unknown as PaginationMeta }
}

export async function getBreakdown(
  id: number,
  seasonId?: number | null,
): Promise<TransportVehicleBreakdown> {
  const response = await apiGet<TransportVehicleBreakdown>(
    `/api/v1/transport-breakdowns/${id}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function recordBreakdown(
  runId: number,
  payload: { reason: string; notes?: string; occurred_at?: string },
  seasonId?: number | null,
): Promise<TransportVehicleBreakdown> {
  const response = await apiPost<TransportVehicleBreakdown>(
    `/api/v1/transport-runs/${runId}/breakdowns`,
    payload,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function closeBreakdown(
  id: number,
  reason: string,
  seasonId?: number | null,
): Promise<TransportVehicleBreakdown> {
  const response = await apiPost<TransportVehicleBreakdown>(
    `/api/v1/transport-breakdowns/${id}/close`,
    { reason },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function cancelBreakdown(
  id: number,
  reason: string,
  seasonId?: number | null,
): Promise<TransportVehicleBreakdown> {
  const response = await apiPost<TransportVehicleBreakdown>(
    `/api/v1/transport-breakdowns/${id}/cancel`,
    { reason },
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function previewReplacement(
  breakdownId: number,
  replacementSeasonVehicleId: number,
  seasonId?: number | null,
): Promise<ReplacementPreview> {
  const response = await apiGet<ReplacementPreview>(
    `/api/v1/transport-breakdowns/${breakdownId}/preview-replacement?replacement_season_vehicle_id=${replacementSeasonVehicleId}`,
    seasonRequestOptions(seasonId),
  )
  return response.data
}

export async function executeReplacement(
  breakdownId: number,
  payload: {
    replacement_season_vehicle_id: number
    reason?: string
    idempotency_key?: string
    reassignments?: Array<{ run_passenger_id: number; target_run_id: number }>
  },
  seasonId?: number | null,
): Promise<{
  breakdown: TransportVehicleBreakdown
  replacement: TransportVehicleReplacement
  run: TransportVehicleRun
}> {
  const response = await apiPost<{
    breakdown: TransportVehicleBreakdown
    replacement: TransportVehicleReplacement
    run: TransportVehicleRun
  }>(`/api/v1/transport-breakdowns/${breakdownId}/replace`, payload, seasonRequestOptions(seasonId))
  return response.data
}
