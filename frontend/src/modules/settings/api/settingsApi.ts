import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

import type { TenantSettings, UpdateTenantSettingsPayload } from '../types/settings'

export async function getTenantSettings(): Promise<TenantSettings> {
  const response = await apiGet<TenantSettings>('/api/v1/tenant-settings')
  return response.data
}

export async function updateTenantSettings(
  payload: UpdateTenantSettingsPayload,
): Promise<TenantSettings> {
  const response = await apiPatch<TenantSettings>('/api/v1/tenant-settings', payload)
  return response.data
}

export async function sendTenantTestEmail(email: string): Promise<string> {
  const response = await apiPost<{ sent: boolean }>('/api/v1/tenant-settings/test-email', {
    email,
  })
  return response.message || 'تم إرسال رسالة الاختبار بنجاح.'
}
