export interface AssetCategory {
  id: number
  name: string
  description: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface ListAssetCategoriesParams {
  search?: string
  is_active?: boolean | ''
}

export interface AssetCategoryPayload {
  name: string
  description?: string | null
  is_active?: boolean
}

export interface UpdateAssetCategoryPayload {
  name?: string
  description?: string | null
  is_active?: boolean
}

export interface AssetCategoryFormState {
  name: string
  description: string
  is_active: boolean
}
