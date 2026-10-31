export type SeasonStatus = 'draft' | 'active' | 'closed'

export interface Season {
  id: number
  code: string
  name: string
  hajj_year: number
  status: SeasonStatus
  operational_starts_on: string
  operational_ends_on: string
  notes: string | null
  first_activated_at: string | null
  activated_at: string | null
  closed_at: string | null
  reopened_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface SeasonsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSeasonsParams {
  status?: SeasonStatus | 'all' | ''
  page?: number
  per_page?: number
}

export interface CreateSeasonPayload {
  code: string
  name: string
  hajj_year: number
  operational_starts_on: string
  operational_ends_on: string
  notes?: string | null
}

export interface UpdateSeasonPayload {
  code?: string
  name?: string
  hajj_year?: number
  operational_starts_on?: string
  operational_ends_on?: string
  notes?: string | null
}

export interface SeasonFormState {
  code: string
  name: string
  hajj_year: string
  operational_starts_on: string
  operational_ends_on: string
  notes: string
}
