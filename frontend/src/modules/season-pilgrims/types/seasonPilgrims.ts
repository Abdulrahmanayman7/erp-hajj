export type SeasonPilgrimStatus =
  | 'registered'
  | 'active'
  | 'completed'
  | 'cancelled'

export interface SeasonPilgrimRef {
  id: number
  full_name: string
}

export interface SeasonProgramRef {
  id: number
  code: string
  name: string
}

export interface SeasonPilgrimGroupRef {
  id: number
  code: string
  name: string
}

export interface SeasonFamilyGroupRef {
  id: number
  name: string
  is_primary_member: boolean
}

export interface SeasonPilgrim {
  id: number
  season_id: number
  reference_number: string
  status: SeasonPilgrimStatus
  pilgrim: SeasonPilgrimRef | null
  program: SeasonProgramRef | null
  pilgrim_group: SeasonPilgrimGroupRef | null
  family_group: SeasonFamilyGroupRef | null
  registered_at: string | null
  activated_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  created_at: string | null
  updated_at: string | null
}

export interface SeasonPilgrimsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSeasonPilgrimsParams {
  search?: string
  status?: SeasonPilgrimStatus | ''
  pilgrim_id?: number
  season_program_id?: number
  season_pilgrim_group_id?: number
  season_family_group_id?: number
  page?: number
  per_page?: number
}
