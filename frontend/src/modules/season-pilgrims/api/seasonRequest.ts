import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'

/** Shared season header helper for season-scoped SeasonPilgrims APIs. */
export function seasonRequestOptions(seasonId?: number | null): {
  headers?: Record<string, string>
} {
  if (seasonId != null && seasonId > 0) {
    return { headers: { 'X-Season-Id': String(seasonId) } }
  }
  const store = useAccommodationSeasonStore()
  return { headers: store.seasonHeaders() }
}

export function requireSeasonId(seasonId?: number | null): number {
  if (seasonId != null && seasonId > 0) return seasonId
  const store = useAccommodationSeasonStore()
  if (store.selectedSeasonId != null && store.selectedSeasonId > 0) {
    return store.selectedSeasonId
  }
  throw new Error('SEASON_REQUIRED')
}
