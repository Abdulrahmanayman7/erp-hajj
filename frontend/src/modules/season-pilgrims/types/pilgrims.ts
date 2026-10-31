export type PilgrimGender = 'male' | 'female'

export interface PilgrimIdentityDocument {
  id: number
  pilgrim_id: number
  document_type: string
  issuing_country: string
  document_number: string
  created_at: string | null
  updated_at: string | null
}

export interface Pilgrim {
  id: number
  full_name: string
  gender: PilgrimGender | null
  nationality: string | null
  phone: string | null
  notes: string | null
  identity_documents?: PilgrimIdentityDocument[]
  has_identity_documents?: boolean
  created_at: string | null
  updated_at: string | null
}

export interface PilgrimsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListPilgrimsParams {
  search?: string
  page?: number
  per_page?: number
}

export interface CreatePilgrimPayload {
  full_name: string
  gender?: PilgrimGender | null
  nationality?: string | null
  phone?: string | null
  notes?: string | null
}

export interface UpdatePilgrimPayload {
  full_name?: string
  gender?: PilgrimGender | null
  nationality?: string | null
  phone?: string | null
  notes?: string | null
}

export interface PilgrimFormState {
  full_name: string
  gender: PilgrimGender | ''
  nationality: string
  phone: string
  notes: string
}
