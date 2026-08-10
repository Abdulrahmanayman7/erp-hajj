export interface DocumentCategory {
  id: number
  name: string
  description: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface ListDocumentCategoriesParams {
  active?: boolean | 1 | 0 | '' | string
}

export interface CreateDocumentCategoryPayload {
  name: string
  description?: string | null
  is_active?: boolean
}

export interface UpdateDocumentCategoryPayload {
  name?: string
  description?: string | null
  is_active?: boolean
}

export interface DocumentCategoryFormState {
  name: string
  description: string
}
