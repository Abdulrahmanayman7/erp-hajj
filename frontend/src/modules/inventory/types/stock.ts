import type { MovementDirection } from './movements'
import type { InventoryMovement } from './movements'

export interface ReceiveStockPayload {
  warehouse_id: number
  inventory_item_id: number
  quantity: number | string
  reason: string
  reference?: string | null
  as_opening?: boolean
}

export interface IssueStockPayload {
  warehouse_id: number
  inventory_item_id: number
  quantity: number | string
  reason: string
  reference?: string | null
}

export interface ReturnStockPayload {
  warehouse_id: number
  inventory_item_id: number
  quantity: number | string
  reason: string
  reference?: string | null
}

export interface TransferStockPayload {
  source_warehouse_id: number
  destination_warehouse_id: number
  inventory_item_id: number
  quantity: number | string
  reason: string
  reference?: string | null
}

export interface AdjustStockPayload {
  warehouse_id: number
  inventory_item_id: number
  direction: MovementDirection
  quantity: number | string
  reason: string
  reference?: string | null
}

export interface TransferStockResult {
  transfer_group_id: string
  transfer_out: InventoryMovement
  transfer_in: InventoryMovement
}

export interface StockActionFormState {
  warehouse_id: number | ''
  inventory_item_id: number | ''
  quantity: string
  reason: string
  reference: string
  as_opening: boolean
  source_warehouse_id: number | ''
  destination_warehouse_id: number | ''
  direction: MovementDirection | ''
}

export type StockActionKind = 'receive' | 'issue' | 'return' | 'transfer' | 'adjust'
