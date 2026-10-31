export interface PilgrimGroupSupervisor {
  id: number
  role: string
  employee: {
    id: number
    full_name: string
    employee_number: string
  } | null
  assigned_at: string | null
  ended_at: string | null
  is_current: boolean
}

export interface SeasonPilgrimGroup {
  id: number
  season_id: number
  code: string
  name: string
  notes: string | null
  season_pilgrims_count: number | null
  supervisors?: PilgrimGroupSupervisor[]
  created_at: string | null
  updated_at: string | null
}

export interface PilgrimGroupFormState {
  code: string
  name: string
  notes: string
}

export interface CreatePilgrimGroupPayload {
  code: string
  name: string
  notes?: string | null
}

export interface UpdatePilgrimGroupPayload {
  code?: string
  name?: string
  notes?: string | null
}
