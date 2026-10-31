import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  cancelMovement,
  completeMovement,
  createMovement,
  createRun,
  planMovement,
  startMovement,
  updateMovement,
} from '../api/movementsApi'
import {
  addRunPassengers,
  assignRunDriver,
  assignRunVehicle,
  cancelPassenger,
  cancelRun,
  transitionRun,
  updateRun,
} from '../api/runsApi'
import type { CreateRunFormState, MovementFormState } from '../types/operations'
import { transportMovementsQueryKey, transportRunsQueryKey } from '../queries/useOperationsQueries'

function invalidateOps(client: ReturnType<typeof useQueryClient>) {
  void client.invalidateQueries({ queryKey: transportMovementsQueryKey })
  void client.invalidateQueries({ queryKey: transportRunsQueryKey })
}

export function useCreateMovementMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { payload: MovementFormState; seasonId?: number | null }) =>
      createMovement(input.payload, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useUpdateMovementMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { id: number; payload: MovementFormState; seasonId?: number | null }) =>
      updateMovement(input.id, input.payload, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function usePlanMovementMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { id: number; seasonId?: number | null }) => planMovement(input.id, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useStartMovementMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { id: number; seasonId?: number | null }) => startMovement(input.id, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useCompleteMovementMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { id: number; seasonId?: number | null }) =>
      completeMovement(input.id, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useCancelMovementMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { id: number; reason: string; seasonId?: number | null }) =>
      cancelMovement(input.id, input.reason, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useCreateRunMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: {
      movementId: number
      payload: CreateRunFormState
      seasonId?: number | null
    }) => createRun(input.movementId, input.payload, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useTransitionRunMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: {
      id: number
      action: 'start-boarding' | 'depart' | 'arrive' | 'complete'
      seasonId?: number | null
    }) => transitionRun(input.id, input.action, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useCancelRunMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { id: number; reason: string; seasonId?: number | null }) =>
      cancelRun(input.id, input.reason, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useAssignRunVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: {
      id: number
      transport_season_vehicle_id: number
      reason?: string | null
      seasonId?: number | null
    }) =>
      assignRunVehicle(
        input.id,
        {
          transport_season_vehicle_id: input.transport_season_vehicle_id,
          reason: input.reason,
        },
        input.seasonId,
      ),
    onSuccess: () => invalidateOps(client),
  })
}

export function useAssignRunDriverMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: {
      id: number
      driver_employee_id?: number | null
      external_driver_id?: number | null
      reason?: string | null
      seasonId?: number | null
    }) =>
      assignRunDriver(
        input.id,
        {
          driver_employee_id: input.driver_employee_id,
          external_driver_id: input.external_driver_id,
          reason: input.reason,
        },
        input.seasonId,
      ),
    onSuccess: () => invalidateOps(client),
  })
}

export function useAddPassengersMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: {
      runId: number
      season_pilgrim_ids: number[]
      seasonId?: number | null
    }) =>
      addRunPassengers(
        input.runId,
        { source: 'individual', season_pilgrim_ids: input.season_pilgrim_ids },
        input.seasonId,
      ),
    onSuccess: () => invalidateOps(client),
  })
}

export function useCancelPassengerMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: { passengerId: number; reason: string; seasonId?: number | null }) =>
      cancelPassenger(input.passengerId, input.reason, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}

export function useUpdateRunMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: {
      id: number
      payload: { capacity_override?: number | null; notes?: string | null }
      seasonId?: number | null
    }) => updateRun(input.id, input.payload, input.seasonId),
    onSuccess: () => invalidateOps(client),
  })
}
