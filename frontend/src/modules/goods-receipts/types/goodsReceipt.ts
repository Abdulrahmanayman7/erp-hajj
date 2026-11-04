export type GoodsReceiptStatus = 'draft' | 'posted'

export interface GoodsReceiptLine {
  id: number
  line_no: number
  purchase_order_line_id: number
  quantity: string
  inventory_item_id: number | null
  inventory_movement_id: number | null
  purchase_order_line?: {
    id: number
    line_no: number
    description: string
    quantity_ordered: string
    quantity_received: string
    quantity_remaining: string
    unit: string
    unit_price: string
    inventory_item_id: number | null
  } | null
  inventory_movement?: {
    id: number
    movement_number: string
    quantity: string
  } | null
}

export interface GoodsReceipt {
  id: number
  grn_number: string
  purchase_order_id: number
  warehouse_id: number | null
  status: GoodsReceiptStatus
  notes: string | null
  posted_at: string | null
  created_at: string | null
  purchase_order?: {
    id: number
    po_number: string
    status: string
    procurement_rfq_id: number
    purchase_request_id: number
  } | null
  warehouse?: {
    id: number
    warehouse_number: string
    name: string
    is_active: boolean
  } | null
  lines?: GoodsReceiptLine[]
}

export interface CreateGoodsReceiptPayload {
  purchase_order_id: number
  warehouse_id?: number | null
  notes?: string
  idempotency_key?: string
  lines: Array<{
    purchase_order_line_id: number
    quantity: number | string
  }>
}
