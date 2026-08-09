export type UserStatus = 'active' | 'disabled'

export type AvatarGroup = 'male' | 'female' | 'neutral'

export interface UserRoleSummary {
  id: number
  code: string
  name: string
}

export interface TenantUser {
  id: number
  name: string
  email: string
  status: UserStatus
  avatar_group: AvatarGroup
  roles: UserRoleSummary[]
  created_at: string | null
  updated_at: string | null
  password_provisioned?: boolean
}

export interface UsersListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface CreateUserPayload {
  name: string
  email: string
  role_ids?: number[]
  send_invite?: boolean
  temporary_password?: string
  temporary_password_confirmation?: string
  avatar_group?: AvatarGroup
}

export interface UpdateUserPayload {
  name?: string
  email?: string
  avatar_group?: AvatarGroup
}
