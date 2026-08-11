export type MovementType =
  | 'opening'
  | 'receipt'
  | 'issue'
  | 'return'
  | 'transfer_out'
  | 'transfer_in'
  | 'adjustment'

export type MovementDirection = 'in' | 'out'

export const MOVEMENT_TYPES: MovementType[] = [
  'opening',
  'receipt',
  'issue',
  'return',
  'transfer_out',
  'transfer_in',
  'adjustment',
]

export interface MovementWarehouseSummary {
  id: number
  warehouse_number: string
  name: string
}

export interface MovementItemCategorySummary {
  id: number
  name: string
}

export interface MovementItemSummary {
  id: number
  item_number: string
  name: string
  unit: string | null
  category?: MovementItemCategorySummary | null
}

export interface MovementPerformerSummary {
  id: number
  name: string
}

export interface InventoryMovement {
  id: number
  movement_number: string
  type: MovementType
  direction: MovementDirection
  quantity: string | number
  balance_before: string | number
  balance_after: string | number
  transfer_group_id: string | null
  reason: string | null
  reference: string | null
  occurred_at: string | null
  correlation_id: string | null
  warehouse: MovementWarehouseSummary | null
  item: MovementItemSummary | null
  performer: MovementPerformerSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface InventoryMovementsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListInventoryMovementsParams {
  warehouse_id?: number | ''
  inventory_item_id?: number | ''
  type?: MovementType | ''
  performed_by?: number | ''
  transfer_group_id?: string
  occurred_from?: string
  occurred_to?: string
  search?: string
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export type InventoryMovementListState = 'loading' | 'error' | 'empty' | 'ready'
