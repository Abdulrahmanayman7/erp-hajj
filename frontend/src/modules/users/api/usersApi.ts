import { apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type { CreateUserPayload, TenantUser, UpdateUserPayload, UsersListMeta } from '../types/users'

export interface ListUsersParams {
  search?: string
  status?: string
  role_id?: number | ''
  page?: number
  per_page?: number
  sort?: string
  direction?: string
}

function toQuery(params: ListUsersParams): string {
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

export async function listUsers(params: ListUsersParams = {}): Promise<{
  data: TenantUser[]
  meta: UsersListMeta
}> {
  const response = await apiGet<TenantUser[]>(`/api/v1/users${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as UsersListMeta,
  }
}

export async function createUser(payload: CreateUserPayload): Promise<TenantUser> {
  const response = await apiPost<TenantUser>('/api/v1/users', payload)
  return response.data
}

export async function updateUser(id: number, payload: UpdateUserPayload): Promise<TenantUser> {
  const response = await apiPatch<TenantUser>(`/api/v1/users/${id}`, payload)
  return response.data
}

export async function disableUser(id: number, reason?: string): Promise<TenantUser> {
  const response = await apiPost<TenantUser>(`/api/v1/users/${id}/disable`, reason ? { reason } : {})
  return response.data
}

export async function enableUser(id: number, reason?: string): Promise<TenantUser> {
  const response = await apiPost<TenantUser>(`/api/v1/users/${id}/enable`, reason ? { reason } : {})
  return response.data
}

export async function syncUserRoles(id: number, roleIds: number[]): Promise<TenantUser> {
  const response = await apiPut<TenantUser>(`/api/v1/users/${id}/roles`, { role_ids: roleIds })
  return response.data
}
