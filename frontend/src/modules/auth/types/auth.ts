export type UserStatus = 'active' | 'disabled'

export type TenantStatus = 'pending' | 'active' | 'suspended' | 'archived'

export interface AuthTenant {
  id: number
  tenant_code: string
  name: string
  status: TenantStatus
  locale: string
  timezone: string
}

export interface AuthRole {
  id: number
  code: string
  name: string
}

export interface AuthUser {
  id: number
  name: string
  email: string
  status: UserStatus
  is_platform_user: boolean
  tenant: AuthTenant | null
  roles: AuthRole[]
  permissions: string[]
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export const AUTH_BLOCK_CODES = [
  'AUTH_ACCOUNT_DISABLED',
  'TENANT_PENDING',
  'TENANT_SUSPENDED',
  'TENANT_ARCHIVED',
  'TENANT_CONTEXT_INVALID',
] as const

export type AuthBlockCode = (typeof AUTH_BLOCK_CODES)[number]

export function isAuthBlockCode(code: string | undefined): code is AuthBlockCode {
  return !!code && (AUTH_BLOCK_CODES as readonly string[]).includes(code)
}
