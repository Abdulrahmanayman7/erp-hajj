export interface ContractCategory {
  id: number
  name: string
  code: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface CategoriesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListCategoriesParams {
  search?: string
  is_active?: boolean | '' | string
  page?: number
  per_page?: number
}

export interface CreateCategoryPayload {
  name: string
  code?: string | null
  is_active?: boolean
}

export interface UpdateCategoryPayload {
  name?: string
  code?: string | null
}

export interface CategoryFormState {
  name: string
  code: string
}
