import { apiGet, type ApiSuccess } from '@/shared/api/http'

export interface HealthData {
  application: string
  version: string
  environment: string
  timestamp: string
}

export function fetchHealth(): Promise<ApiSuccess<HealthData>> {
  return apiGet<HealthData>('/api/v1/health')
}
