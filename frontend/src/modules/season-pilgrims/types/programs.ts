export interface SeasonProgram {
  id: number
  season_id: number
  code: string
  name: string
  description: string | null
  is_active: boolean
  season_pilgrims_count: number | null
  created_at: string | null
  updated_at: string | null
}

export interface ProgramFormState {
  code: string
  name: string
  description: string
}

export interface CreateSeasonProgramPayload {
  code: string
  name: string
  description?: string | null
}

export interface UpdateSeasonProgramPayload {
  code?: string
  name?: string
  description?: string | null
}
