export interface InventoryCategory {
  id: number
  name: string
  description: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface ListInventoryCategoriesParams {
  search?: string
  is_active?: boolean | ''
}

export interface InventoryCategoryPayload {
  name: string
  description?: string | null
  is_active?: boolean
}

export interface UpdateInventoryCategoryPayload {
  name?: string
  description?: string | null
  is_active?: boolean
}

export interface InventoryCategoryFormState {
  name: string
  description: string
  is_active: boolean
}
