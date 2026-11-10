import { apiGet, apiPost } from '@/shared/api/http'

import type { ExecutiveAlert } from '../types/executiveDashboard'

export async function listExecutiveAlerts(seasonId: number): Promise<ExecutiveAlert[]> {
  const response = await apiGet<ExecutiveAlert[]>(
    `/api/v1/executive-alerts?season_id=${encodeURIComponent(String(seasonId))}`,
  )
  return response.data
}

export async function acknowledgeExecutiveAlert(alertId: number): Promise<ExecutiveAlert> {
  const response = await apiPost<ExecutiveAlert>(
    `/api/v1/executive-alerts/${encodeURIComponent(String(alertId))}/acknowledge`,
  )
  return response.data
}
