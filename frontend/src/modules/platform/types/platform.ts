import type { TenantStatus } from '@/modules/auth/types/auth'

export type PlatformTenantStatus = TenantStatus

export interface PlatformTenantOwner {
  id: number
  name: string
  email: string
}

export interface PlatformTenantContact {
  name: string | null
  email: string | null
  phone: string | null
}

export interface PlatformTenant {
  id: number
  name: string
  code: string
  tenant_code: string
  status: PlatformTenantStatus
  locale: string
  timezone: string
  contact: PlatformTenantContact
  notes: string | null
  owner: PlatformTenantOwner | null
  users_count: number
  suspended_at: string | null
  archived_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface PlatformTenantCreateResult extends PlatformTenant {
  invite_sent: boolean
  invite_code: string | null
  password_provisioned: boolean
}

export interface PlatformTenantsListMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ListPlatformTenantsParams {
  search?: string
  status?: PlatformTenantStatus | ''
  page?: number
  per_page?: number
}

export interface ProvisionTenantOwnerPayload {
  name: string
  email: string
  send_invite?: boolean
  temporary_password?: string
}

export interface ProvisionTenantPayload {
  tenant_code: string
  name: string
  locale?: string
  timezone?: string
  status?: 'active' | 'pending'
  contact_name?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  notes?: string | null
  mail_from_address?: string | null
  mail_from_name?: string | null
  owner: ProvisionTenantOwnerPayload
}

export interface UpdatePlatformTenantPayload {
  name?: string
  locale?: string
  timezone?: string
  contact_name?: string | null
  contact_email?: string | null
  contact_phone?: string | null
  notes?: string | null
}

export interface PlatformSetupStatus {
  available: boolean
}

export interface BootstrapPlatformAdminPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface BootstrapPlatformAdminResult {
  available: boolean
  user: {
    id: number
    name: string
    email: string
    is_platform_user: boolean
  }
}
