import { apiGet } from '@/shared/api/http'

import type { ExecutiveDashboardData, ExecutiveDashboardMeta } from '../types/executiveDashboard'

export async function getExecutiveDashboard(seasonId: number): Promise<{
  data: ExecutiveDashboardData
  meta?: ExecutiveDashboardMeta
}> {
  const response = await apiGet<ExecutiveDashboardData>(
    `/api/v1/executive-dashboard?season_id=${encodeURIComponent(String(seasonId))}`,
  )
  return { data: response.data, meta: response.meta as ExecutiveDashboardMeta | undefined }
}
