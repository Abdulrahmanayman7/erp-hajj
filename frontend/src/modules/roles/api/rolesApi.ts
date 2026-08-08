import { apiDelete, apiGet, apiPatch, apiPost, apiPut } from '@/shared/api/http'

import type {
  CreateRolePayload,
  PermissionModuleGroup,
  RoleSummary,
  UpdateRolePayload,
} from '../types/roles'

export interface ListRolesParams {
  search?: string
  is_active?: boolean | ''
  page?: number
  per_page?: number
}

function toQuery(params: ListRolesParams | Record<string, string | number | boolean | undefined | ''>): string {
  const query = new URLSearchParams()
  Object.entries(params as Record<string, string | number | boolean | undefined | ''>).forEach(
    ([key, value]) => {
      if (value === undefined || value === null || value === '') {
        return
      }
      query.set(key, String(value))
    },
  )
  const qs = query.toString()
  return qs ? `?${qs}` : ''
}

export async function listRoles(params: ListRolesParams = {}): Promise<{
  data: RoleSummary[]
  meta: { current_page: number; per_page: number; total: number; last_page: number }
}> {
  const response = await apiGet<RoleSummary[]>(`/api/v1/roles${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as {
      current_page: number
      per_page: number
      total: number
      last_page: number
    },
  }
}

export async function getRole(id: number): Promise<RoleSummary> {
  const response = await apiGet<RoleSummary>(`/api/v1/roles/${id}`)
  return response.data
}

export async function createRole(payload: CreateRolePayload): Promise<RoleSummary> {
  const response = await apiPost<RoleSummary>('/api/v1/roles', payload)
  return response.data
}

export async function updateRole(id: number, payload: UpdateRolePayload): Promise<RoleSummary> {
  const response = await apiPatch<RoleSummary>(`/api/v1/roles/${id}`, payload)
  return response.data
}

export async function activateRole(id: number): Promise<RoleSummary> {
  const response = await apiPost<RoleSummary>(`/api/v1/roles/${id}/activate`)
  return response.data
}

export async function deactivateRole(id: number): Promise<RoleSummary> {
  const response = await apiPost<RoleSummary>(`/api/v1/roles/${id}/deactivate`)
  return response.data
}

export async function deleteRole(id: number): Promise<void> {
  await apiDelete(`/api/v1/roles/${id}`)
}

export async function syncRolePermissions(
  id: number,
  permissionIds: number[],
): Promise<RoleSummary> {
  const response = await apiPut<RoleSummary>(`/api/v1/roles/${id}/permissions`, {
    permission_ids: permissionIds,
  })
  return response.data
}

export async function listPermissionCatalog(search?: string): Promise<PermissionModuleGroup[]> {
  const response = await apiGet<{ modules: PermissionModuleGroup[] }>(
    `/api/v1/permissions${toQuery({ search })}`,
  )
  return response.data.modules
}
