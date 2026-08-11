export interface WarehouseOrgUnitSummary {
  id: number
  name: string
  code: string | null
}

export interface WarehouseEmployeeSummary {
  id: number
  full_name: string
  employee_number: string | null
}

export interface WarehouseActorSummary {
  id: number
  name: string
}

export interface Warehouse {
  id: number
  warehouse_number: string
  name: string
  description: string | null
  location: string | null
  is_active: boolean
  notes: string | null
  organization_unit: WarehouseOrgUnitSummary | null
  responsible_employee: WarehouseEmployeeSummary | null
  created_by: WarehouseActorSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface WarehousesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListWarehousesParams {
  search?: string
  is_active?: boolean | ''
  organization_unit_id?: number | ''
  responsible_employee_id?: number | ''
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface WarehousePayload {
  name: string
  description?: string | null
  location?: string | null
  organization_unit_id?: number | null
  responsible_employee_id?: number | null
  is_active?: boolean
  notes?: string | null
}

export interface WarehouseFormState {
  name: string
  description: string
  location: string
  organization_unit_id: number | ''
  responsible_employee_id: number | ''
  is_active: boolean
  notes: string
}

export type WarehouseListState = 'loading' | 'error' | 'empty' | 'ready'
