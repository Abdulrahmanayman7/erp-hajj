import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'

/** Season header helper for season-scoped Transportation APIs. */
export function seasonRequestOptions(seasonId?: number | null): {
  headers?: Record<string, string>
} {
  if (seasonId != null && seasonId > 0) {
    return { headers: { 'X-Season-Id': String(seasonId) } }
  }
  const store = useAccommodationSeasonStore()
  return { headers: store.seasonHeaders() }
}
