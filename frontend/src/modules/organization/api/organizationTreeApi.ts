import { apiGet } from '@/shared/api/http'

export interface OrganizationTreeOwner {
  id: number
  name: string
  email: string
  status: string
}

export interface OrganizationTreeEmployee {
  id: number
  name: string
  employee_number: string | null
  status: string
  position_id: number | null
  supervisor_employee_id: number | null
}

export interface OrganizationTreePosition {
  id: number
  name: string
  code: string | null
  is_active: boolean
}

export interface OrganizationTreeUnit {
  id: number
  name: string
  code: string | null
  type: string
  status: string
  parent_id: number | null
  sort_order: number
  manager_user_id: number | null
  positions: OrganizationTreePosition[]
  employees: OrganizationTreeEmployee[]
  children: OrganizationTreeUnit[]
}

export interface OrganizationTreePayload {
  tenant: {
    id: number
    name: string
    code: string
    status: string
  }
  ownership: {
    owner: OrganizationTreeOwner | null
  }
  organization: {
    units: OrganizationTreeUnit[]
  }
}

export async function fetchOrganizationTree(): Promise<OrganizationTreePayload> {
  const response = await apiGet<OrganizationTreePayload>('/api/v1/organization-tree')
  return response.data
}
