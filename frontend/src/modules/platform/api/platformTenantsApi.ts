import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  ListPlatformTenantsParams,
  PlatformTenant,
  PlatformTenantCreateResult,
  PlatformTenantsListMeta,
  ProvisionTenantPayload,
  UpdatePlatformTenantPayload,
} from '../types/platform'

function toQuery(params: ListPlatformTenantsParams): string {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') {
      return
    }
    query.set(key, String(value))
  })
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listPlatformTenants(params: ListPlatformTenantsParams = {}): Promise<{
  data: PlatformTenant[]
  meta: PlatformTenantsListMeta
}> {
  const response = await apiGet<PlatformTenant[]>(`/api/v1/platform/tenants${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as PlatformTenantsListMeta,
  }
}

export async function getPlatformTenant(id: number): Promise<PlatformTenant> {
  const response = await apiGet<PlatformTenant>(`/api/v1/platform/tenants/${id}`)
  return response.data
}

export async function createPlatformTenant(
  payload: ProvisionTenantPayload,
): Promise<PlatformTenantCreateResult> {
  const response = await apiPost<PlatformTenantCreateResult>('/api/v1/platform/tenants', payload)
  return response.data
}

export async function updatePlatformTenant(
  id: number,
  payload: UpdatePlatformTenantPayload,
): Promise<PlatformTenant> {
  const response = await apiPatch<PlatformTenant>(`/api/v1/platform/tenants/${id}`, payload)
  return response.data
}

export async function activatePlatformTenant(id: number): Promise<PlatformTenant> {
  const response = await apiPost<PlatformTenant>(`/api/v1/platform/tenants/${id}/activate`)
  return response.data
}

export async function suspendPlatformTenant(id: number, reason: string): Promise<PlatformTenant> {
  const response = await apiPost<PlatformTenant>(`/api/v1/platform/tenants/${id}/suspend`, {
    reason,
  })
  return response.data
}

export async function archivePlatformTenant(id: number, reason: string): Promise<PlatformTenant> {
  const response = await apiPost<PlatformTenant>(`/api/v1/platform/tenants/${id}/archive`, {
    reason,
  })
  return response.data
}

export async function transferPlatformTenantOwnership(
  id: number,
  newOwnerId: number,
): Promise<PlatformTenant> {
  const response = await apiPost<PlatformTenant>(
    `/api/v1/platform/tenants/${id}/transfer-ownership`,
    { new_owner_id: newOwnerId },
  )
  return response.data
}

export interface PlatformTenantUser {
  id: number
  name: string
  email: string
  status: string
  is_owner: boolean
}

export async function listPlatformTenantUsers(id: number): Promise<PlatformTenantUser[]> {
  const response = await apiGet<PlatformTenantUser[]>(`/api/v1/platform/tenants/${id}/users`)
  return response.data
}
