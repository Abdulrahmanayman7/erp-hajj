export type DemandGroupStatus = 'draft' | 'active' | 'frozen' | 'closed'

export interface DemandGroupMember {
  purchase_request_id: number
  item_id: number
  pr_number?: string
  title?: string
  status?: string
  version_no?: number
  season_id?: number | null
  beneficiary_organization_unit_id?: number | null
  estimated_total_priced?: string
  has_unpriced_lines?: boolean
}

export interface DemandGroup {
  id: number
  group_number: string
  title: string
  notes: string | null
  status: DemandGroupStatus
  season_id: number | null
  beneficiary_organization_unit_id: number | null
  created_by: number
  frozen_at: string | null
  freeze_reason: string | null
  closed_at: string | null
  created_at: string | null
  updated_at: string | null
  hidden_member_count: number
  members?: DemandGroupMember[]
  planning_totals?: {
    priced_total: string | null
    unpriced_member_count: number
  }
  scope_warnings?: string[]
}

export interface DemandGroupListFilters {
  status?: DemandGroupStatus | ''
  q?: string
  page?: number
  per_page?: number
}

export type ControlReportKind = 'approval-latency' | 'overdue' | 'outcomes' | 'compliance'

export interface ControlReportFilters {
  from: string
  to: string
  season_id?: number | ''
  organization_unit_id?: number | ''
  priority?: string
}
