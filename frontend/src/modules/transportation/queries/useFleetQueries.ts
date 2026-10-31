import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import { listTransportDrivers } from '../api/driversApi'
import { listTransportPoints } from '../api/pointsApi'
import { listSeasonVehicles } from '../api/seasonFleetApi'
import { getTransportVehicle, listTransportVehicles } from '../api/vehiclesApi'
import type {
  ListDriversParams,
  ListSeasonVehiclesParams,
  ListVehiclesParams,
} from '../types/fleet'
import type { ListPointsParams } from '../types/points'

export const transportVehiclesQueryKey = ['transport-vehicles'] as const
export const transportDriversQueryKey = ['transport-external-drivers'] as const
export const seasonFleetQueryKey = ['transport-season-vehicles'] as const
export const transportPointsQueryKey = ['transport-points'] as const
export const transportVehicleDetailQueryKey = (id: number) =>
  [...transportVehiclesQueryKey, 'detail', id] as const

export function useTransportVehiclesQuery(params: MaybeRefOrGetter<ListVehiclesParams>) {
  return useQuery({
    queryKey: computed(() => [...transportVehiclesQueryKey, 'list', toValue(params)]),
    queryFn: () => listTransportVehicles(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useTransportDriversQuery(params: MaybeRefOrGetter<ListDriversParams>) {
  return useQuery({
    queryKey: computed(() => [...transportDriversQueryKey, 'list', toValue(params)]),
    queryFn: () => listTransportDrivers(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useSeasonFleetQuery(
  params: MaybeRefOrGetter<ListSeasonVehiclesParams>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...seasonFleetQueryKey, 'list', toValue(params)]),
    queryFn: () => listSeasonVehicles(toValue(params)),
    enabled: computed(() => toValue(enabled)),
    placeholderData: keepPreviousData,
  })
}

export function useTransportPointsQuery(params: MaybeRefOrGetter<ListPointsParams>) {
  return useQuery({
    queryKey: computed(() => [...transportPointsQueryKey, 'list', toValue(params)]),
    queryFn: () => listTransportPoints(toValue(params)),
    placeholderData: keepPreviousData,
  })
}

export function useTransportVehicleQuery(id: MaybeRefOrGetter<number | null>) {
  return useQuery({
    queryKey: computed(() => {
      const value = toValue(id)
      return value == null
        ? [...transportVehiclesQueryKey, 'detail', 'unknown']
        : transportVehicleDetailQueryKey(value)
    }),
    queryFn: () => getTransportVehicle(toValue(id)!),
    enabled: computed(() => {
      const value = toValue(id)
      return value != null && Number.isFinite(value) && value > 0
    }),
  })
}
