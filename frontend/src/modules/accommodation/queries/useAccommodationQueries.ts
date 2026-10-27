import type { AssignmentListFilters } from '../api/accommodationApi'
import { sessionGeneration } from '@/shared/api/sessionLifetime'
import { useQuery } from '@tanstack/vue-query'
import { storeToRefs } from 'pinia'
import { computed, type MaybeRefOrGetter, toValue } from 'vue'

import {
  getFacility,
  listAssignments,
  listFacilities,
  listFacilityScopes,
  listPositions,
  listSeasonFacilities,
  listSeasonPositions,
  listSeasonUnits,
  listStructures,
  listSupervisorScopes,
  listUnits,
} from '../api/accommodationApi'
import { fetchSeasonContext, listSeasonsForAccommodation } from '../api/seasonLookupsApi'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'

import { collectAccommodationPages } from './useAccommodationOptions'

export const accommodationQueryKey = ['accommodation'] as const

function seasonKey() {
  const store = useAccommodationSeasonStore()
  return [store.selectedSeasonId ?? 'default', store.contextGeneration]
}

export function useAccommodationSeasonContextQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  const store = useAccommodationSeasonStore()
  const { selectedSeasonId } = storeToRefs(store)

  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'season-context', selectedSeasonId.value ?? 'default', store.contextGeneration]),
    queryFn: async () => {
      const context = store.contextGeneration
      const session = sessionGeneration.value
      const payload = await fetchSeasonContext()
      store.applyContext(payload, context, session)
      return payload
    },
    enabled: computed(() => toValue(enabled)),
    retry: false,
  })
}

export function useAccommodationSeasonsQuery(enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'seasons']),
    queryFn: () => listSeasonsForAccommodation({ page: 1, per_page: 100 }),
    enabled: computed(() => toValue(enabled)),
    retry: false,
  })
}

export function useFacilitiesQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'facilities', seasonKey(), toValue(page)]),
    queryFn: () => listFacilities(toValue(page)),
    enabled: computed(() => toValue(enabled)),
  })
}

export function useFacilityQuery(id: MaybeRefOrGetter<number | null>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'facility', seasonKey(), toValue(id)]),
    queryFn: () => getFacility(toValue(id)!),
    enabled: computed(() => toValue(enabled) && !!toValue(id)),
  })
}

export function useStructuresQuery(
  facilityId: MaybeRefOrGetter<number | null>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'structures', seasonKey(), toValue(facilityId)]),
    queryFn: async () => ({ data: await collectAccommodationPages((page, size) => listStructures(toValue(facilityId)!, page, size)) }),
    enabled: computed(() => toValue(enabled) && !!toValue(facilityId)),
  })
}

export function useUnitsQuery(
  facilityId: MaybeRefOrGetter<number | null>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'units', seasonKey(), toValue(facilityId)]),
    queryFn: async () => ({ data: await collectAccommodationPages((page, size) => listUnits(toValue(facilityId)!, page, size)) }),
    enabled: computed(() => toValue(enabled) && !!toValue(facilityId)),
  })
}

export function usePositionsQuery(
  unitId: MaybeRefOrGetter<number | null>,
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'positions', seasonKey(), toValue(unitId)]),
    queryFn: async () => ({ data: await collectAccommodationPages((page, size) => listPositions(toValue(unitId)!, page, size)) }),
    enabled: computed(() => toValue(enabled) && !!toValue(unitId)),
  })
}

export function useSeasonFacilitiesQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'season-facilities', seasonKey(), toValue(page)]),
    queryFn: () => listSeasonFacilities(toValue(page)),
    enabled: computed(() => toValue(enabled)),
  })
}

export function useSeasonUnitsQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'season-units', seasonKey(), toValue(page)]),
    queryFn: () => listSeasonUnits(toValue(page)),
    enabled: computed(() => toValue(enabled)),
  })
}

export function useSeasonPositionsQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'season-positions', seasonKey(), toValue(page)]),
    queryFn: () => listSeasonPositions(toValue(page)),
    enabled: computed(() => toValue(enabled)),
  })
}

export function useAssignmentsQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true, filters: MaybeRefOrGetter<AssignmentListFilters> = {}) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'assignments', seasonKey(), toValue(page), toValue(filters)]),
    queryFn: ({ signal }) => listAssignments(toValue(page), 15, toValue(filters), signal),
    enabled: computed(() => toValue(enabled)),
  })
}

export function useFacilityScopesQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'facility-scopes', seasonKey(), toValue(page)]),
    queryFn: () => listFacilityScopes(toValue(page)),
    enabled: computed(() => toValue(enabled)),
  })
}

export function useSupervisorScopesQuery(page: MaybeRefOrGetter<number>, enabled: MaybeRefOrGetter<boolean> = true) {
  return useQuery({
    queryKey: computed(() => [...accommodationQueryKey, 'supervisor-scopes', seasonKey(), toValue(page)]),
    queryFn: () => listSupervisorScopes(toValue(page)),
    enabled: computed(() => toValue(enabled)),
  })
}
