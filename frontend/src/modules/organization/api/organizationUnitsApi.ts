import { apiDelete, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  CreateOrganizationUnitPayload,
  ListOrganizationUnitsParams,
  OrganizationUnit,
  UpdateOrganizationUnitPayload,
} from '../types/organization'

function toQuery(params: ListOrganizationUnitsParams): string {
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

export async function listOrganizationUnits(
  params: ListOrganizationUnitsParams = {},
): Promise<{ data: OrganizationUnit[] }> {
  const response = await apiGet<OrganizationUnit[]>(
    `/api/v1/organization-units${toQuery({ view: 'tree', ...params })}`,
  )
  return { data: response.data }
}

export async function listOrganizationUnitsFlat(
  params: ListOrganizationUnitsParams = {},
): Promise<{ data: OrganizationUnit[] }> {
  const response = await apiGet<OrganizationUnit[]>(
    `/api/v1/organization-units${toQuery({ view: 'flat', status: 'active', ...params })}`,
  )
  return { data: response.data }
}

export async function getOrganizationUnit(id: number): Promise<OrganizationUnit> {
  const response = await apiGet<OrganizationUnit>(`/api/v1/organization-units/${id}`)
  return response.data
}

export async function createOrganizationUnit(
  payload: CreateOrganizationUnitPayload,
): Promise<OrganizationUnit> {
  const response = await apiPost<OrganizationUnit>('/api/v1/organization-units', payload)
  return response.data
}

export async function updateOrganizationUnit(
  id: number,
  payload: UpdateOrganizationUnitPayload,
): Promise<OrganizationUnit> {
  const response = await apiPatch<OrganizationUnit>(`/api/v1/organization-units/${id}`, payload)
  return response.data
}

export async function moveOrganizationUnit(
  id: number,
  parentId: number | null,
): Promise<OrganizationUnit> {
  const response = await apiPost<OrganizationUnit>(`/api/v1/organization-units/${id}/move`, {
    parent_id: parentId,
  })
  return response.data
}

export async function activateOrganizationUnit(id: number): Promise<OrganizationUnit> {
  const response = await apiPost<OrganizationUnit>(`/api/v1/organization-units/${id}/activate`)
  return response.data
}

export async function deactivateOrganizationUnit(id: number): Promise<OrganizationUnit> {
  const response = await apiPost<OrganizationUnit>(`/api/v1/organization-units/${id}/deactivate`)
  return response.data
}

export async function deleteOrganizationUnit(id: number): Promise<void> {
  await apiDelete(`/api/v1/organization-units/${id}`)
}
