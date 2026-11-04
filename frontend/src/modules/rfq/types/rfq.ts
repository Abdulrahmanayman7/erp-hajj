export type RfqStatus = 'draft' | 'published' | 'awarded' | 'cancelled'

export type OfferStatus = 'draft' | 'submitted' | 'withdrawn' | 'selected' | 'rejected'

export interface RfqActorRef {
  id: number
  name: string
}

export interface RfqLine {
  id?: number
  line_no: number
  purchase_request_line_id?: number | null
  description: string
  specifications?: string | null
  quantity: string | number
  unit: string
  need_date?: string | null
  inventory_item_id?: number | null
}

export interface RfqPurchaseRequestSummary {
  id: number
  pr_number: string
  status: string
  title: string
  procurement_engaged_at?: string | null
  procurement_engagement_ref?: string | null
}

export interface RfqSupplierSummary {
  id: number
  supplier_number: string
  display_name: string
  status: string
}

export interface RfqInvitation {
  id: number
  procurement_rfq_id?: number
  supplier_id: number
  status: string
  invited_at?: string | null
  supplier?: RfqSupplierSummary | null
}

export interface SupplierOfferLine {
  id?: number
  line_no: number
  procurement_rfq_line_id: number
  quantity: string | number
  unit_price: string | number
  lead_time_days?: number | null
  line_total?: string
  rfq_line?: {
    id: number
    line_no: number
    description: string
    unit: string
  } | null
}

export interface SupplierOffer {
  id: number
  offer_number: string
  procurement_rfq_id: number
  supplier_id: number
  status: OfferStatus
  currency: string
  notes?: string | null
  lead_time_days?: number | null
  lines_total?: string | null
  submitted_at?: string | null
  withdrawn_at?: string | null
  created_at?: string | null
  supplier?: RfqSupplierSummary | null
  lines?: SupplierOfferLine[]
}

export interface ProcurementRfq {
  id: number
  rfq_number: string
  purchase_request_id: number
  season_id?: number | null
  status: RfqStatus
  title: string
  notes?: string | null
  published_at?: string | null
  published_by?: RfqActorRef | null
  awarded_at?: string | null
  awarded_by?: RfqActorRef | null
  selected_supplier_id?: number | null
  selection_rationale?: string | null
  selected_supplier?: RfqSupplierSummary | null
  cancelled_at?: string | null
  cancelled_by?: RfqActorRef | null
  cancellation_reason?: string | null
  created_by?: RfqActorRef | null
  created_at?: string | null
  updated_at?: string | null
  purchase_request?: RfqPurchaseRequestSummary
  lines?: RfqLine[]
  invitations?: RfqInvitation[]
  offers?: SupplierOffer[]
}

export interface CreateRfqPayload {
  purchase_request_id: number
  title?: string
  notes?: string
  lines?: Array<{
    purchase_request_line_id?: number | null
    description: string
    specifications?: string | null
    quantity: number | string
    unit: string
    need_date?: string | null
    inventory_item_id?: number | null
  }>
}

export interface CreateOfferPayload {
  supplier_id: number
  currency?: string
  notes?: string
  lead_time_days?: number | null
  lines: Array<{
    procurement_rfq_line_id: number
    quantity: number | string
    unit_price: number | string
    lead_time_days?: number | null
  }>
}

export interface SelectSupplierPayload {
  supplier_id: number
  selection_rationale: string
  offer_id?: number | null
}
