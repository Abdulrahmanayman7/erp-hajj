export interface RegisterSeasonPilgrimPayload {
  pilgrim_id: number
  pilgrim_identity_document_id?: number | null
  season_program_id?: number | null
  season_pilgrim_group_id?: number | null
}

export interface EnrollFormState {
  pilgrim_id: number | ''
  season_program_id: number | ''
  season_pilgrim_group_id: number | ''
  season_family_group_id: number | ''
}

export interface ParticipationEditState {
  season_program_id: number | ''
  season_pilgrim_group_id: number | ''
  season_family_group_id: number | ''
  program_reason: string
}
