export type PurchaseOrderStatus =
  | 'draft'
  | 'issued'
  | 'partially_received'
  | 'received'
  | 'closed'
  | 'cancelled'

export interface PurchaseOrderLine {
  id: number
  line_no: number
  procurement_rfq_line_id: number | null
  supplier_offer_line_id: number | null
  description: string
  specifications: string | null
  quantity_ordered: string
  quantity_received: string
  quantity_remaining?: string
  unit: string
  unit_price: string
  inventory_item_id: number | null
}

export interface PurchaseOrderQuantitySummary {
  ordered: string
  received: string
  remaining: string
}

export interface PurchaseOrder {
  id: number
  po_number: string
  procurement_rfq_id: number
  purchase_request_id: number
  supplier_id: number
  supplier_offer_id: number | null
  contract_id: number | null
  season_id: number | null
  status: PurchaseOrderStatus
  currency: string
  notes: string | null
  issued_at: string | null
  cancelled_at: string | null
  cancellation_reason: string | null
  closed_at: string | null
  closed_by?: { id: number; name: string | null } | null
  close_reason: string | null
  created_at: string | null
  supplier?: {
    id: number
    supplier_number: string
    display_name: string
    status: string
  } | null
  rfq?: {
    id: number
    rfq_number: string
    status: string
    title: string
  } | null
  purchase_request?: {
    id: number
    pr_number: string
    status: string
    title: string
  } | null
  winning_offer?: {
    id: number
    offer_number: string
    status: string
    currency: string
  } | null
  contract?: {
    id: number
    contract_number: string
    title: string
    status: string
    supplier_id: number | null
  } | null
  lines?: PurchaseOrderLine[]
  quantity_summary?: PurchaseOrderQuantitySummary
}

export interface CreatePurchaseOrderPayload {
  rfq_id: number
  notes?: string
  contract_id?: number | null
  currency?: string
}

export interface UpdatePurchaseOrderPayload {
  notes?: string | null
  contract_id?: number | null
  currency?: string
}
