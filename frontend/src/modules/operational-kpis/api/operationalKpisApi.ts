import { apiGet } from '@/shared/api/http'

import type { OperationalKpisData, OperationalKpisMeta } from '../types/operationalKpis'

export async function getOperationalKpis(seasonId: number): Promise<{
  data: OperationalKpisData
  meta?: OperationalKpisMeta
}> {
  const response = await apiGet<OperationalKpisData>(
    `/api/v1/operational-kpis?season_id=${encodeURIComponent(String(seasonId))}`,
  )
  return { data: response.data, meta: response.meta as OperationalKpisMeta | undefined }
}
