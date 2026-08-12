import { apiGet } from '@/shared/api/http'

import type { AuditListMeta, AuditLogDetail, AuditLogSummary, ListAuditLogsParams } from '../types/audit'

function toQuery(params: ListAuditLogsParams): string {
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

export async function listAuditLogs(params: ListAuditLogsParams = {}): Promise<{
  data: AuditLogSummary[]
  meta: AuditListMeta
}> {
  const response = await apiGet<AuditLogSummary[]>(`/api/v1/audit-logs${toQuery(params)}`)
  return {
    data: response.data,
    meta: response.meta as unknown as AuditListMeta,
  }
}

export async function getAuditLog(id: number): Promise<AuditLogDetail> {
  const response = await apiGet<AuditLogDetail>(`/api/v1/audit-logs/${id}`)
  return response.data
}

export { toQuery as auditParamsToQuery }
