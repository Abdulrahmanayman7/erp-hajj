export type EmployeeStatus = 'active' | 'inactive'

export interface EmployeeOrgUnitSummary {
  id: number
  name: string
  code: string
}

export interface EmployeePositionSummary {
  id: number
  name: string
  code: string | null
}

export interface EmployeeSupervisorSummary {
  id: number
  employee_number: string
  full_name: string
  organization_unit: EmployeeOrgUnitSummary | null
}

export interface EmployeeUserSummary {
  id: number
  name: string
  email: string
  status: string
}

export interface Employee {
  id: number
  employee_number: string
  full_name: string
  phone: string | null
  email: string | null
  status: EmployeeStatus
  hire_date: string | null
  notes: string | null
  organization_unit: EmployeeOrgUnitSummary | null
  position: EmployeePositionSummary | null
  supervisor: EmployeeSupervisorSummary | null
  user: EmployeeUserSummary | null
  created_at: string | null
  updated_at: string | null
}

export interface EmployeesListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListEmployeesParams {
  search?: string
  status?: 'active' | 'inactive' | 'all' | ''
  organization_unit_id?: number | ''
  supervisor_id?: number | ''
  position_id?: number | ''
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

export interface CreateEmployeePayload {
  full_name: string
  organization_unit_id: number
  position_id?: number | null
  phone?: string | null
  email?: string | null
  hire_date?: string | null
  notes?: string | null
}

export interface UpdateEmployeePayload {
  full_name?: string
  organization_unit_id?: number
  position_id?: number | null
  phone?: string | null
  email?: string | null
  hire_date?: string | null
  notes?: string | null
}

export interface EmployeeFormState {
  full_name: string
  organization_unit_id: number | ''
  position_id: number | ''
  phone: string
  email: string
  hire_date: string
  notes: string
}
