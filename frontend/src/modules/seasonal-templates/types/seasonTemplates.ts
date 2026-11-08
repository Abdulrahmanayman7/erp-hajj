export type TemplateStatus = 'draft' | 'approved' | 'archived'
export const TEMPLATE_STATUSES: readonly TemplateStatus[] = ['draft', 'approved', 'archived']

export interface TemplateItemSelection {
  codes?: string[]
}

export interface SeasonTemplateItem {
  entity_kind: string
  sort_order: number
  selection: TemplateItemSelection | null
  /** Independent snapshot captured with the template (S08 expansion). */
  has_snapshot?: boolean
  snapshot_row_count?: number
  snapshot_anchor?: string | null
  snapshot_codes?: string[]
}

export interface TemplateUserSummary {
  id: number
  name: string
}

export interface SeasonTemplate {
  id: number
  template_number: string
  name: string
  description: string | null
  status: TemplateStatus
  matrix_version: string
  items: SeasonTemplateItem[]
  approved_at: string | null
  archived_at: string | null
  created_at: string | null
  updated_at: string | null
  created_by: TemplateUserSummary | null
  approved_by: TemplateUserSummary | null
}

export interface SeasonTemplatesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListSeasonTemplatesParams {
  q?: string
  status?: TemplateStatus | 'all' | ''
  page?: number
  per_page?: number
}

/** One contract-definition snapshot row (allowlisted fields only; validated again by the server). */
export interface ContractDefinitionSnapshotRow {
  code?: string
  title: string
  contract_category_id: number
  counterparty_kind: ContractCounterpartyKind
  currency?: string
  start_date: string
  end_date?: string
  notes?: string
}

export interface TemplateItemPayload {
  entity_kind: string
  selection?: { codes: string[] } | null
  /** Only ever built for `contract_definitions` (rows typed in the editor); other kinds are captured by the server. */
  snapshot?: { anchor: string; rows: ContractDefinitionSnapshotRow[] }
}

export interface CreateSeasonTemplatePayload {
  name: string
  description?: string | null
  items: TemplateItemPayload[]
  /** Capture independent snapshots of the selected kinds from this (tenant) season while saving. */
  capture_season_id?: number
}

export type UpdateSeasonTemplatePayload = Partial<CreateSeasonTemplatePayload>

export type ContractCounterpartyKind = 'person' | 'organization' | 'other'
export const CONTRACT_COUNTERPARTY_KINDS: readonly ContractCounterpartyKind[] = ['organization', 'person', 'other']

/** Editable row of the contract-definitions snapshot editor (all inputs kept as strings/ids until built). */
export interface ContractDefinitionRowForm {
  code: string
  title: string
  contract_category_id: number | ''
  counterparty_kind: ContractCounterpartyKind
  currency: string
  start_date: string
  end_date: string
  notes: string
}

export interface TemplateFormState {
  name: string
  description: string
  entity_kinds: string[]
  /** Optional natural-key codes per entity kind, newline/comma separated. */
  codes: Record<string, string>
  /** Optional season to capture snapshots from when saving. */
  capture_season_id: number | ''
  /** Date anchor of the contract-definitions snapshot (dates are shifted relative to it on copy). */
  contract_anchor: string
  /** Contract-definitions snapshot rows typed in the editor (empty = no snapshot sent). */
  contract_rows: ContractDefinitionRowForm[]
}
