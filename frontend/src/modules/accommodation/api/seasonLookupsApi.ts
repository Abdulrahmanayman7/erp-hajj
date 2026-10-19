import { apiGet } from '@/shared/api/http'

import type {
  AccommodationSeason,
  PaginationMeta,
  SeasonContextPayload,
  SeasonFamilyGroupLookup,
  SeasonPilgrimLookup,
} from '../types/accommodation'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'

function seasonOptions() {
  return { headers: useAccommodationSeasonStore().seasonHeaders() }
}

export async function listSeasonsForAccommodation(params: {
  page?: number
  per_page?: number
} = {}): Promise<{ data: AccommodationSeason[]; meta: PaginationMeta }> {
  const query = new URLSearchParams()
  if (params.page) query.set('page', String(params.page))
  if (params.per_page) query.set('per_page', String(params.per_page))
  const qs = query.toString()
  const response = await apiGet<AccommodationSeason[]>(`/api/v1/seasons${qs ? `?${qs}` : ''}`)
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}

export async function fetchSeasonContext(): Promise<SeasonContextPayload> {
  const response = await apiGet<SeasonContextPayload>('/api/v1/season-context', seasonOptions())
  return response.data
}

export async function listSeasonPilgrimsLookup(params: {
  search?: string
  page?: number
  per_page?: number
} = {}): Promise<{ data: SeasonPilgrimLookup[]; meta: PaginationMeta }> {
  const query = new URLSearchParams()
  if (params.search) query.set('search', params.search)
  if (params.page) query.set('page', String(params.page))
  if (params.per_page) query.set('per_page', String(params.per_page))
  const qs = query.toString()
  const response = await apiGet<SeasonPilgrimLookup[]>(
    `/api/v1/season-pilgrims${qs ? `?${qs}` : ''}`,
    seasonOptions(),
  )
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}

export async function listSeasonFamilyGroupsLookup(params: {
  search?: string
  page?: number
  per_page?: number
} = {}): Promise<{ data: SeasonFamilyGroupLookup[]; meta: PaginationMeta }> {
  const query = new URLSearchParams()
  if (params.search) query.set('search', params.search)
  if (params.page) query.set('page', String(params.page))
  if (params.per_page) query.set('per_page', String(params.per_page))
  const qs = query.toString()
  const response = await apiGet<SeasonFamilyGroupLookup[]>(
    `/api/v1/season-family-groups${qs ? `?${qs}` : ''}`,
    seasonOptions(),
  )
  return {
    data: response.data,
    meta: response.meta as unknown as PaginationMeta,
  }
}
