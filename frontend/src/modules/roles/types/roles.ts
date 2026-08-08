export interface RoleSummary {
  id: number
  name: string
  code: string
  description: string | null
  is_system: boolean
  is_active: boolean
  users_count: number
  permissions_count: number
  permissions?: string[]
  created_at: string | null
  updated_at: string | null
}

export interface PermissionItem {
  id: number
  name: string
  display_name: string
  description: string | null
  high_risk?: boolean
}

export interface PermissionModuleGroup {
  module: string
  display_name: string
  permissions: PermissionItem[]
}

export interface CreateRolePayload {
  name: string
  code?: string
  description?: string
}

export interface UpdateRolePayload {
  name?: string
  description?: string | null
}
