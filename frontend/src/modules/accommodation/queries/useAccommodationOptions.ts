import { assertSession, sessionGeneration } from '@/shared/api/sessionLifetime'
import { getActivePinia } from 'pinia'
import { computed, toValue, type MaybeRefOrGetter } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { listFacilities, listSeasonFacilities, listSeasonUnits, listSeasonPositions, listUnits } from '../api/accommodationApi'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type { AccommodationUnit, PaginationMeta } from '../types/accommodation'

/** Selects must include every page, independently of the visible table page. */
export async function collectAccommodationPages<T>(fetchPage: (page: number, perPage: number) => Promise<{ data: T[]; meta: PaginationMeta }>): Promise<T[]> {
  const generation = sessionGeneration.value
  const store = getActivePinia() ? useAccommodationSeasonStore() : null
  const context = store?.contextGeneration
  const assertContext = () => {
    assertSession(generation)
    if (context !== store?.contextGeneration) throw new DOMException('Season changed', 'AbortError')
  }
  const first = await fetchPage(1, 100)
  assertContext()
  const rows = [...first.data]
  for (let page = 2; page <= first.meta.last_page; page++) {
    assertContext()
    const next = await fetchPage(page, 100)
    assertContext()
    rows.push(...next.data)
  }
  return rows
}

export function useAccommodationOptions(enabled: MaybeRefOrGetter<boolean> = true, options: { positions?: boolean } = {}) {
  const store = useAccommodationSeasonStore()
  const facilities = useQuery({
    queryKey: computed(() => ['accommodation', 'facility-options', store.selectedSeasonId ?? 'default', store.contextGeneration]),
    queryFn: () => collectAccommodationPages(listFacilities),
    enabled: computed(() => toValue(enabled)),
  })
  const seasonFacilities = useQuery({
    queryKey: computed(() => ['accommodation', 'season-facility-options', store.selectedSeasonId ?? 'default', store.contextGeneration]),
    queryFn: () => collectAccommodationPages(listSeasonFacilities),
    enabled: computed(() => toValue(enabled)),
  })
  const units = useQuery({
    queryKey: computed(() => ['accommodation', 'season-unit-options', store.selectedSeasonId ?? 'default', store.contextGeneration]),
    queryFn: () => collectAccommodationPages(listSeasonUnits),
    enabled: computed(() => toValue(enabled)),
  })
  const positions = useQuery({
    queryKey: computed(() => ['accommodation', 'season-position-options', store.selectedSeasonId ?? 'default', store.contextGeneration]),
    queryFn: () => collectAccommodationPages(listSeasonPositions),
    enabled: computed(() => toValue(enabled) && options.positions !== false),
  })
  const unitDetails = useQuery({
    queryKey: computed(() => ['accommodation', 'unit-directory', store.selectedSeasonId ?? 'default', store.contextGeneration, facilities.data.value?.map(row => row.id), units.data.value?.map(row => row.facility_id)]),
    queryFn: async () => {
      const generation = sessionGeneration.value
      const context = store.contextGeneration
      const allocated = new Set(units.data.value?.map(row => row.facility_id))
      const places = (facilities.data.value ?? []).filter(place => allocated.has(place.id))
      const rows: AccommodationUnit[] = []
      for (let offset = 0; offset < places.length; offset += 4) {
        const batch = await Promise.all(places.slice(offset, offset + 4).map(place =>
          collectAccommodationPages((page, size) => listUnits(place.id, page, size))))
        assertSession(generation)
        if (context !== store.contextGeneration) throw new DOMException('Season changed', 'AbortError')
        rows.push(...batch.flat())
      }
      return rows
    },
    enabled: computed(() => toValue(enabled) && facilities.isSuccess.value && units.isSuccess.value),
  })
  function facilityName(id: number): string {
    return facilities.data.value?.find(row => row.id === id)?.name ?? `#${id}`
  }
  function unitLabel(id: number): string {
    const unit = units.data.value?.find(row => row.id === id)
    const code = unitDetails.data.value?.find(row => row.id === unit?.unit_id)?.code
    return unit ? `${facilityName(unit.facility_id)} · ${code ?? unit.unit_id}` : `#${id}`
  }
  const isLoading = computed(() => facilities.isFetching.value || units.isFetching.value || positions.isFetching.value)
  const isError = computed(() => facilities.isError.value || units.isError.value || positions.isError.value || seasonFacilities.isError.value)
  async function retry() {
    await Promise.all([facilities.refetch(), seasonFacilities.refetch(), units.refetch(), positions.refetch(), unitDetails.refetch()])
  }
  return { facilities, seasonFacilities, units, positions, unitDetails, facilityName, unitLabel, isLoading, isError, retry }
}
