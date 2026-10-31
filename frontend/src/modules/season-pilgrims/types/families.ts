export interface FamilyMember {
  season_pilgrim_id: number
  reference_number: string
  status: string
  is_primary_member: boolean
  pilgrim: { id: number; full_name: string } | null
}

export interface SeasonFamilyGroup {
  id: number
  season_id: number
  name: string
  notes: string | null
  members_count: number | null
  members?: FamilyMember[]
  created_at: string | null
  updated_at: string | null
}

export interface FamilyFormState {
  name: string
  notes: string
}

export interface CreateFamilyGroupPayload {
  name: string
  notes?: string | null
}

export interface UpdateFamilyGroupPayload {
  name?: string
  notes?: string | null
}
