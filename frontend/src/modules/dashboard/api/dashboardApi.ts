import { apiGet } from '@/shared/api/http'

import type { DashboardData } from '../types/dashboard'

export async function getDashboard(): Promise<DashboardData> {
  const response = await apiGet<DashboardData>('/api/v1/dashboard')
  return response.data
}
