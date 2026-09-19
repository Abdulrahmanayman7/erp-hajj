export type AssetStatus =
  | 'available'
  | 'in_use'
  | 'maintenance'
  | 'damaged'
  | 'retired'
  | 'lost'

export type AssetCondition = 'good' | 'fair' | 'damaged' | 'unknown'

export const ASSET_STATUSES: AssetStatus[] = [
  'available',
  'in_use',
  'maintenance',
  'damaged',
  'retired',
  'lost',
]

export const ASSET_CONDITIONS: AssetCondition[] = ['good', 'fair', 'damaged', 'unknown']

export const RETURN_NEXT_STATUSES: AssetStatus[] = [
  'available',
  'maintenance',
  'damaged',
  'retired',
]

export type AssetLifecycleAction =
  | 'assign'
  | 'return'
  | 'maintenance'
  | 'restore'
  | 'retire'
  | 'declare_lost'

export interface AssetCategorySummary {
  id: number
  name: string
  is_active?: boolean
}

export interface AssetWarehouseSummary {
  id: number
  warehouse_number: string | null
  name: string
}

export interface AssetOrgUnitSummary {
  id: number
  name: string
  code: string | null
}

export interface AssetEmployeeSummary {
  id: number
  full_name: string
  employee_number: string | null
}

export interface AssetActorSummary {
  id: number
  name: string
}

export interface AssetCurrentCustody {
  id: number
  custody_number: string
  assigned_at: string | null
  expected_return_at: string | null
  employee: AssetEmployeeSummary | null
}

export interface AssetStatusTransition {
  id: number
  from_status: AssetStatus | string | null
  to_status: AssetStatus | string
  reason: string | null
  custody_id: number | null
  correlation_id: string | null
  performed_by: AssetActorSummary | null
  created_at: string | null
}

export interface Asset {
  id: number
  asset_number: string
  name: string
  description: string | null
  serial_number: string | null
  barcode: string | null
  status: AssetStatus | string
  condition: AssetCondition | string | null
  purchase_value: string | number | null
  acquisition_date: string | null
  notes: string | null
  category: AssetCategorySummary | null
  warehouse: AssetWarehouseSummary | null
  organization_unit: AssetOrgUnitSummary | null
  current_custody: AssetCurrentCustody | null
  created_by: AssetActorSummary | null
  created_at: string | null
  updated_at: string | null
  transitions?: AssetStatusTransition[]
  custodies?: import('./custodies').AssetCustody[]
}

export interface AssetsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListAssetsParams {
  search?: string
  status?: AssetStatus | ''
  category_id?: number | ''
  warehouse_id?: number | ''
  organization_unit_id?: number | ''
  employee_id?: number | ''
  serial_number?: string
  acquisition_from?: string
  acquisition_to?: string
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface AssetPayload {
  name: string
  description?: string | null
  category_id?: number | null
  serial_number?: string | null
  barcode?: string | null
  condition?: AssetCondition | string | null
  warehouse_id?: number | null
  organization_unit_id?: number | null
  purchase_value?: number | string | null
  acquisition_date?: string | null
  notes?: string | null
}

export interface AssetFormState {
  name: string
  description: string
  category_id: number | ''
  serial_number: string
  barcode: string
  condition: AssetCondition | ''
  warehouse_id: number | ''
  organization_unit_id: number | ''
  purchase_value: string
  acquisition_date: string
  notes: string
  /** UI-only: optional custody assignee after save. Never sent on POST/PATCH asset. */
  employee_id: number | ''
}

export interface AssignCustodyPayload {
  employee_id: number
  expected_return_at?: string | null
  condition_at_assignment?: AssetCondition | string | null
  assignment_notes?: string | null
}

export interface AssignCustodyFormState {
  employee_id: number | ''
  expected_return_at: string
  condition_at_assignment: AssetCondition | ''
  assignment_notes: string
}

export interface ReturnCustodyPayload {
  next_status: AssetStatus | string
  condition_at_return?: AssetCondition | string | null
  return_notes?: string | null
}

export interface ReturnCustodyFormState {
  next_status: AssetStatus | ''
  condition_at_return: AssetCondition | ''
  return_notes: string
}

export interface LifecycleReasonFormState {
  reason: string
}

export type AssetsListState = 'loading' | 'error' | 'empty' | 'ready'
