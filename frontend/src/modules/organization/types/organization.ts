export type OrganizationUnitType = 'department' | 'section' | 'unit'
export type OrganizationUnitStatus = 'active' | 'inactive'

export interface OrganizationUnitManager {
  id: number
  name: string
  email: string
  status: string
}

export interface OrganizationUnit {
  id: number
  name: string
  code: string
  type: OrganizationUnitType
  status: OrganizationUnitStatus
  parent_id: number | null
  sort_order: number
  manager: OrganizationUnitManager | null
  children_count: number
  depth: number | null
  children?: OrganizationUnit[]
  created_at: string | null
  updated_at: string | null
}

export interface CreateOrganizationUnitPayload {
  name: string
  code: string
  type: OrganizationUnitType
  parent_id?: number | null
  manager_user_id?: number | null
  sort_order?: number
}

export interface UpdateOrganizationUnitPayload {
  name?: string
  type?: OrganizationUnitType
  manager_user_id?: number | null
  sort_order?: number
}

export interface ListOrganizationUnitsParams {
  view?: 'tree' | 'flat'
  status?: 'all' | 'active' | 'inactive'
  search?: string
  parent_id?: number
}
