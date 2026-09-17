import { apiGet, apiPost } from '@/shared/api/http'

import type {
  BootstrapPlatformAdminPayload,
  BootstrapPlatformAdminResult,
  PlatformSetupStatus,
} from '../types/platform'

export async function fetchPlatformSetupStatus(): Promise<PlatformSetupStatus> {
  const response = await apiGet<PlatformSetupStatus>('/api/v1/platform/setup/status')
  return response.data
}

export async function bootstrapPlatformAdministrator(
  payload: BootstrapPlatformAdminPayload,
): Promise<BootstrapPlatformAdminResult> {
  const response = await apiPost<BootstrapPlatformAdminResult>('/api/v1/platform/setup', payload)
  return response.data
}
