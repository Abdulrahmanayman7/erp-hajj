import type { AssetCondition, AssetEmployeeSummary, AssetActorSummary } from './assets'

export type CustodyStatus = 'active' | 'returned'

export const CUSTODY_STATUSES: CustodyStatus[] = ['active', 'returned']

export interface CustodyAssetSummary {
  id: number
  asset_number: string
  name: string
  status: string | null
}

export interface AssetCustody {
  id: number
  custody_number: string
  status: CustodyStatus | string
  assigned_at: string | null
  expected_return_at: string | null
  returned_at: string | null
  condition_at_assignment: AssetCondition | string | null
  condition_at_return: AssetCondition | string | null
  assignment_notes: string | null
  return_notes: string | null
  is_overdue: boolean
  asset: CustodyAssetSummary | null
  employee: AssetEmployeeSummary | null
  assigned_by: AssetActorSummary | null
  returned_by: AssetActorSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface CustodiesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListCustodiesParams {
  asset_id?: number | ''
  employee_id?: number | ''
  status?: CustodyStatus | ''
  assigned_from?: string
  assigned_to?: string
  overdue?: boolean | ''
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}
