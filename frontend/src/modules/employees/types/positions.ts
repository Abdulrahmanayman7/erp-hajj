export interface Position {
  id: number
  name: string
  code: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface PositionsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListPositionsParams {
  search?: string
  is_active?: boolean | '' | string
  page?: number
  per_page?: number
}

export interface CreatePositionPayload {
  name: string
  code?: string | null
}

export interface UpdatePositionPayload {
  name?: string
  code?: string | null
}

export interface PositionFormState {
  name: string
  code: string
}
