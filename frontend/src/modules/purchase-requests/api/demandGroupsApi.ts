import { apiDelete, apiDownloadBlob, apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type {
  ControlReportFilters,
  ControlReportKind,
  DemandGroup,
  DemandGroupListFilters,
  DemandGroupStatus,
} from '../types/demandGroups'

const groupsBase = '/api/v1/purchase-demand-groups'
const reportsBase = '/api/v1/purchase-control-reports'

function toQuery(filters: Record<string, unknown>): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    params.set(key, String(value))
  })
  const qs = params.toString()
  return qs ? `?${qs}` : ''
}

export async function listDemandGroups(
  filters: DemandGroupListFilters = {},
): Promise<{ data: DemandGroup[]; meta: Record<string, number> }> {
  const response = await apiGet<DemandGroup[]>(`${groupsBase}${toQuery(filters)}`)
  return { data: response.data, meta: (response.meta as Record<string, number>) ?? {} }
}

export async function getDemandGroup(id: number): Promise<DemandGroup> {
  const response = await apiGet<DemandGroup>(`${groupsBase}/${id}`)
  return response.data
}

export async function createDemandGroup(payload: {
  title: string
  notes?: string
  season_id?: number | null
  beneficiary_organization_unit_id?: number | null
}): Promise<DemandGroup> {
  const response = await apiPost<DemandGroup>(groupsBase, payload)
  return response.data
}

export async function updateDemandGroup(
  id: number,
  payload: Partial<{ title: string; notes: string | null; season_id: number | null; beneficiary_organization_unit_id: number | null }>,
): Promise<DemandGroup> {
  const response = await apiPatch<DemandGroup>(`${groupsBase}/${id}`, payload)
  return response.data
}

export async function transitionDemandGroup(
  id: number,
  to: DemandGroupStatus,
  reason?: string,
): Promise<DemandGroup> {
  const response = await apiPost<DemandGroup>(`${groupsBase}/${id}/transition`, { to, reason })
  return response.data
}

export async function linkDemandGroupItem(
  id: number,
  purchaseRequestId: number,
): Promise<DemandGroup> {
  const response = await apiPost<DemandGroup>(`${groupsBase}/${id}/items`, {
    purchase_request_id: purchaseRequestId,
  })
  return response.data
}

export async function unlinkDemandGroupItem(
  id: number,
  purchaseRequestId: number,
): Promise<DemandGroup> {
  const response = await apiDelete<DemandGroup>(`${groupsBase}/${id}/items/${purchaseRequestId}`)
  return response.data
}

export async function fetchControlReport(
  kind: ControlReportKind,
  filters: ControlReportFilters,
): Promise<Record<string, unknown>> {
  const response = await apiGet<Record<string, unknown>>(`${reportsBase}/${kind}${toQuery(filters)}`)
  return response.data
}

export function controlReportCsvPath(kind: ControlReportKind, filters: ControlReportFilters): string {
  return `${reportsBase}/${kind}/export.csv${toQuery(filters)}`
}

export async function downloadControlReportCsv(
  kind: ControlReportKind,
  filters: ControlReportFilters,
): Promise<{ blob: Blob; filename: string | null }> {
  return apiDownloadBlob(controlReportCsvPath(kind, filters))
}
