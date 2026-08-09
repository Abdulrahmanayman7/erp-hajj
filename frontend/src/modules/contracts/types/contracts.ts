export type ContractStatus =
  | 'draft'
  | 'in_review'
  | 'approved'
  | 'signed'
  | 'executing'
  | 'closed'
  | 'renewed'
  | 'expired'
  | 'cancelled'

export type CounterpartyKind = 'person' | 'organization' | 'other'

export interface ContractCategorySummary {
  id: number
  name: string
  code: string | null
  is_active?: boolean
}

export interface ContractEmployeeSummary {
  id: number
  employee_number: string
  full_name: string
}

export interface ContractOrgUnitSummary {
  id: number
  name: string
  code: string
}

export interface ContractActorSummary {
  id: number
  name: string
}

export interface ContractRenewalChildSummary {
  id: number
  contract_number: string
  status: ContractStatus
}

export interface ContractTransition {
  id: number
  from_status: ContractStatus | null
  to_status: ContractStatus
  comment: string | null
  actor: ContractActorSummary | null
  correlation_id: string | null
  created_at: string | null
}

export interface Contract {
  id: number
  contract_number: string
  title: string
  status: ContractStatus
  counterparty_name: string
  counterparty_kind: CounterpartyKind
  start_date: string
  end_date: string | null
  value: string | null
  currency: string
  notes: string | null
  is_expiring_soon: boolean
  category: ContractCategorySummary | null
  employee: ContractEmployeeSummary | null
  organization_unit: ContractOrgUnitSummary | null
  created_by: ContractActorSummary | null
  renewed_from_contract_id: number | null
  renewal_child?: ContractRenewalChildSummary | null
  transitions?: ContractTransition[]
  created_at: string | null
  updated_at: string | null
}

export interface ContractsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListContractsParams {
  search?: string
  status?: ContractStatus | 'all' | ''
  category_id?: number | ''
  employee_id?: number | ''
  organization_unit_id?: number | ''
  expiring_soon?: boolean | 1 | 0 | '' | string
  start_date_from?: string
  start_date_to?: string
  end_date_from?: string
  end_date_to?: string
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface CreateContractPayload {
  title: string
  contract_category_id: number
  counterparty_name: string
  counterparty_kind?: CounterpartyKind
  employee_id?: number | null
  organization_unit_id?: number | null
  start_date: string
  end_date?: string | null
  value?: number | string | null
  currency?: string | null
  notes?: string | null
}

export interface UpdateContractPayload {
  title?: string
  contract_category_id?: number
  counterparty_name?: string
  counterparty_kind?: CounterpartyKind
  employee_id?: number | null
  organization_unit_id?: number | null
  start_date?: string
  end_date?: string | null
  value?: number | string | null
  currency?: string | null
  notes?: string | null
}

export interface ContractTransitionPayload {
  comment?: string | null
}

export interface RenewContractResult {
  source: Contract
  successor: Contract
}

export interface ContractFormState {
  title: string
  contract_category_id: number | ''
  counterparty_name: string
  counterparty_kind: CounterpartyKind
  employee_id: number | ''
  organization_unit_id: number | ''
  start_date: string
  end_date: string
  value: string
  currency: string
  notes: string
}

export type ContractLifecycleAction =
  | 'submit_review'
  | 'return_draft'
  | 'approve'
  | 'sign'
  | 'execute'
  | 'close'
  | 'cancel'
  | 'renew'
