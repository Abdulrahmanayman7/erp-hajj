export type InventoryUnit =
  | 'piece'
  | 'box'
  | 'pack'
  | 'set'
  | 'kg'
  | 'g'
  | 'liter'
  | 'ml'
  | 'meter'
  | 'cm'

export const INVENTORY_UNITS: InventoryUnit[] = [
  'piece',
  'box',
  'pack',
  'set',
  'kg',
  'g',
  'liter',
  'ml',
  'meter',
  'cm',
]

export interface InventoryItemCategorySummary {
  id: number
  name: string
  is_active?: boolean
}

export interface InventoryItemActorSummary {
  id: number
  name: string
}

export interface InventoryItem {
  id: number
  item_number: string
  name: string
  description: string | null
  unit: InventoryUnit | string
  barcode: string | null
  minimum_stock: string | number | null
  is_active: boolean
  notes: string | null
  category: InventoryItemCategorySummary | null
  created_by: InventoryItemActorSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface InventoryItemsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListInventoryItemsParams {
  search?: string
  category_id?: number | ''
  is_active?: boolean | ''
  unit?: InventoryUnit | ''
  low_stock?: boolean | ''
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface InventoryItemPayload {
  name: string
  description?: string | null
  category_id?: number | null
  unit: InventoryUnit | string
  barcode?: string | null
  minimum_stock?: number | string | null
  is_active?: boolean
  notes?: string | null
}

export interface InventoryItemFormState {
  name: string
  description: string
  category_id: number | ''
  unit: InventoryUnit | ''
  barcode: string
  minimum_stock: string
  is_active: boolean
  notes: string
}

export type InventoryItemListState = 'loading' | 'error' | 'empty' | 'ready'
