import type { PaginationMeta } from './fleet'

export type MovementStatus = 'draft' | 'planned' | 'in_progress' | 'completed' | 'cancelled'
export type RunStatus = 'planned' | 'boarding' | 'departed' | 'arrived' | 'completed' | 'cancelled'
export type PassengerStatus =
  | 'assigned'
  | 'boarded'
  | 'arrived'
  | 'no_show'
  | 'cancelled'

export const MOVEMENT_STATUSES: MovementStatus[] = [
  'draft',
  'planned',
  'in_progress',
  'completed',
  'cancelled',
]

export const RUN_STATUSES: RunStatus[] = [
  'planned',
  'boarding',
  'departed',
  'arrived',
  'completed',
  'cancelled',
]

export interface TransportPointSummary {
  id: number
  code: string
  name: string
  point_type?: string
}

export interface TransportMovement {
  id: number
  season_id: number
  movement_number: string
  origin_point_id: number
  origin?: TransportPointSummary | null
  destination_point_id: number
  destination?: TransportPointSummary | null
  planned_departure_at: string | null
  planned_arrival_at: string | null
  status: MovementStatus
  planned_at: string | null
  started_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  runs_count: number | null
  runs?: TransportVehicleRun[]
  notes: string | null
  created_at: string | null
  updated_at: string | null
}

export interface RunVehicleAssignment {
  assignment_id: number
  transport_season_vehicle_id: number
  vehicle_number: string | null
  plate_number: string | null
  vehicle_class: string | null
  service_level: string | null
  operational_capacity: number | null
  assigned_at: string | null
  ended_at: string | null
  reason: string | null
}

export interface RunCrewPerson {
  id: number
  full_name: string
}

export interface RunDriverAssignment {
  assignment_id: number
  driver_type: 'employee' | 'external' | string
  employee: RunCrewPerson | null
  external_driver: RunCrewPerson | null
  assigned_at: string | null
  ended_at: string | null
  reason: string | null
}

export interface RunSupervisorAssignment {
  assignment_id: number
  employee: RunCrewPerson | null
  assigned_at: string | null
  ended_at: string | null
  reason: string | null
}

export interface TransportVehicleRun {
  id: number
  season_id: number
  transport_movement_id: number
  movement?: {
    id: number
    movement_number: string
    status: MovementStatus
    origin: TransportPointSummary | null
    destination: TransportPointSummary | null
    planned_departure_at: string | null
    planned_arrival_at: string | null
  } | null
  run_number: string
  status: RunStatus
  capacity_override: number | null
  effective_capacity: number | null
  occupied_count: number | null
  current_vehicle: RunVehicleAssignment | null
  current_driver: RunDriverAssignment | null
  current_supervisor: RunSupervisorAssignment | null
  vehicle_history?: RunVehicleAssignment[]
  driver_history?: RunDriverAssignment[]
  supervisor_history?: RunSupervisorAssignment[]
  boarding_started_at: string | null
  departed_at: string | null
  arrived_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  notes: string | null
  created_at: string | null
  updated_at: string | null
}

export interface TransportRunPassenger {
  id: number
  season_id: number
  vehicle_run_id: number
  season_pilgrim_id: number
  season_pilgrim: {
    id: number
    reference_number: string | null
    status: string
    full_name: string | null
    season_program_id: number | null
  } | null
  status: PassengerStatus
  assignment_source: string
  source_group_id: number | null
  source_group_label: string | null
  entitlement_status: string | null
  entitlement_override: boolean
  assigned_at: string | null
  boarded_at: string | null
  arrived_at: string | null
  no_show_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
}

export interface MovementFormState {
  origin_point_id: string | number | ''
  destination_point_id: string | number | ''
  planned_departure_at: string
  planned_arrival_at: string
  notes: string
}

export interface CreateRunFormState {
  transport_season_vehicle_id: string | number | ''
  capacity_override: string
  notes: string
}

export interface ListMovementsParams {
  status?: MovementStatus | ''
  page?: number
  per_page?: number
  seasonId?: number | null
}

export type { PaginationMeta }

export function availableSeats(run: Pick<TransportVehicleRun, 'effective_capacity' | 'occupied_count'>): number | null {
  if (run.effective_capacity == null) return null
  return Math.max(0, run.effective_capacity - (run.occupied_count ?? 0))
}
