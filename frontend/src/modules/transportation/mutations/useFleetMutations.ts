import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  activateTransportDriver,
  createTransportDriver,
  deactivateTransportDriver,
  deleteTransportDriver,
  updateTransportDriver,
} from '../api/driversApi'
import {
  activateTransportPoint,
  createTransportPoint,
  deactivateTransportPoint,
  deleteTransportPoint,
  updateTransportPoint,
} from '../api/pointsApi'
import {
  activateSeasonVehicle,
  createSeasonVehicle,
  deactivateSeasonVehicle,
  deleteSeasonVehicle,
  setSeasonVehicleDefaultCrew,
  updateSeasonVehicle,
} from '../api/seasonFleetApi'
import {
  activateTransportVehicle,
  createTransportVehicle,
  deactivateTransportVehicle,
  deleteTransportVehicle,
  updateTransportVehicle,
  updateTransportVehicleLinks,
} from '../api/vehiclesApi'
import {
  seasonFleetQueryKey,
  transportDriversQueryKey,
  transportPointsQueryKey,
  transportVehicleDetailQueryKey,
  transportVehiclesQueryKey,
} from '../queries/useFleetQueries'
import type { DriverFormState, SeasonVehicleFormState, VehicleFormState } from '../types/fleet'
import type { PointFormState } from '../types/points'

export function useCreateVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: VehicleFormState) => {
      if (payload.vehicle_class === '') {
        throw new Error('vehicle_class required')
      }
      return createTransportVehicle({
        plate_number: payload.plate_number,
        vehicle_class: payload.vehicle_class,
        notes: payload.notes,
      })
    },
    onSuccess: () => client.invalidateQueries({ queryKey: transportVehiclesQueryKey }),
  })
}

export function useUpdateVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: Partial<{ plate_number: string; vehicle_class: string; notes: string | null }>
    }) => updateTransportVehicle(id, payload),
    onSuccess: () => client.invalidateQueries({ queryKey: transportVehiclesQueryKey }),
  })
}

export function useDeleteVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteTransportVehicle(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportVehiclesQueryKey }),
  })
}

export function useActivateVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateTransportVehicle(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportVehiclesQueryKey }),
  })
}

export function useDeactivateVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateTransportVehicle(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportVehiclesQueryKey }),
  })
}

export function useCreateDriverMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: DriverFormState) => createTransportDriver(payload),
    onSuccess: () => client.invalidateQueries({ queryKey: transportDriversQueryKey }),
  })
}

export function useUpdateDriverMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: Partial<{ full_name: string; phone: string | null; notes: string | null }>
    }) => updateTransportDriver(id, payload),
    onSuccess: () => client.invalidateQueries({ queryKey: transportDriversQueryKey }),
  })
}

export function useDeleteDriverMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteTransportDriver(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportDriversQueryKey }),
  })
}

export function useActivateDriverMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateTransportDriver(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportDriversQueryKey }),
  })
}

export function useDeactivateDriverMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateTransportDriver(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportDriversQueryKey }),
  })
}

export function useCreateSeasonVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      payload,
      seasonId,
    }: {
      payload: SeasonVehicleFormState
      seasonId?: number | null
    }) => createSeasonVehicle(payload, seasonId),
    onSuccess: () => client.invalidateQueries({ queryKey: seasonFleetQueryKey }),
  })
}

export function useUpdateSeasonVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
      seasonId,
    }: {
      id: number
      payload: Partial<{
        operational_capacity: number
        service_level: string
        notes: string | null
      }>
      seasonId?: number | null
    }) => updateSeasonVehicle(id, payload, seasonId),
    onSuccess: () => client.invalidateQueries({ queryKey: seasonFleetQueryKey }),
  })
}

export function useDeleteSeasonVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deleteSeasonVehicle(id, seasonId),
    onSuccess: () => client.invalidateQueries({ queryKey: seasonFleetQueryKey }),
  })
}

export function useActivateSeasonVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      activateSeasonVehicle(id, seasonId),
    onSuccess: () => client.invalidateQueries({ queryKey: seasonFleetQueryKey }),
  })
}

export function useDeactivateSeasonVehicleMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ id, seasonId }: { id: number; seasonId?: number | null }) =>
      deactivateSeasonVehicle(id, seasonId),
    onSuccess: () => client.invalidateQueries({ queryKey: seasonFleetQueryKey }),
  })
}

export function useUpdateVehicleLinksMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: { asset_id: number | null; contract_id: number | null }
    }) => updateTransportVehicleLinks(id, payload),
    onSuccess: (vehicle) => {
      void client.invalidateQueries({ queryKey: transportVehiclesQueryKey })
      void client.invalidateQueries({ queryKey: transportVehicleDetailQueryKey(vehicle.id) })
    },
  })
}

export function useSetDefaultCrewMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      seasonId,
      payload,
    }: {
      id: number
      seasonId?: number | null
      payload: {
        default_driver_employee_id: number | null
        default_external_driver_id: number | null
        default_supervisor_employee_id: number | null
      }
    }) => setSeasonVehicleDefaultCrew(id, payload, seasonId),
    onSuccess: () => client.invalidateQueries({ queryKey: seasonFleetQueryKey }),
  })
}

export function useCreatePointMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (payload: PointFormState) => createTransportPoint(payload),
    onSuccess: () => client.invalidateQueries({ queryKey: transportPointsQueryKey }),
  })
}

export function useUpdatePointMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: Partial<{ code: string; name: string; point_type: string; notes: string | null }>
    }) => updateTransportPoint(id, payload),
    onSuccess: () => client.invalidateQueries({ queryKey: transportPointsQueryKey }),
  })
}

export function useDeletePointMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteTransportPoint(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportPointsQueryKey }),
  })
}

export function useActivatePointMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => activateTransportPoint(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportPointsQueryKey }),
  })
}

export function useDeactivatePointMutation() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deactivateTransportPoint(id),
    onSuccess: () => client.invalidateQueries({ queryKey: transportPointsQueryKey }),
  })
}
