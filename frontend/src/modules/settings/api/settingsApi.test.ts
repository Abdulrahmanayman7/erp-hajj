import { beforeEach, describe, expect, it, vi } from 'vitest'

import * as settingsApi from './settingsApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPatch: vi.fn(),
  apiPost: vi.fn(),
}))

import { apiGet, apiPatch, apiPost } from '@/shared/api/http'

const sample = {
  general: {
    name: 'رفيع',
    contact_name: null,
    contact_email: null,
    contact_phone: null,
  },
  regional: {
    timezone: 'Asia/Riyadh',
    locale: 'ar',
    locale_editable: false as const,
  },
  technical: {
    status: 'unavailable' as const,
    deliverable: false,
    mail_mailer: null,
    mail_host: null,
    mail_port: null,
    mail_encryption: null,
    mail_username: null,
    mail_password_configured: false,
    mail_from_address: null,
    mail_from_name: null,
  },
}

describe('settingsApi', () => {
  beforeEach(() => {
    vi.mocked(apiGet).mockReset()
    vi.mocked(apiPatch).mockReset()
    vi.mocked(apiPost).mockReset()
  })

  it('loads tenant settings', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: sample,
    })

    const data = await settingsApi.getTenantSettings()
    expect(data.regional.locale).toBe('ar')
    expect(apiGet).toHaveBeenCalledWith('/api/v1/tenant-settings')
  })

  it('patches tenant settings', async () => {
    vi.mocked(apiPatch).mockResolvedValue({
      success: true,
      message: '',
      data: {
        ...sample,
        regional: { ...sample.regional, timezone: 'Africa/Cairo' },
      },
    })

    const data = await settingsApi.updateTenantSettings({
      regional: { timezone: 'Africa/Cairo' },
    })
    expect(data.regional.timezone).toBe('Africa/Cairo')
    expect(apiPatch).toHaveBeenCalledWith('/api/v1/tenant-settings', {
      regional: { timezone: 'Africa/Cairo' },
    })
  })

  it('sends test email', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: 'تم إرسال رسالة الاختبار بنجاح.',
      data: { sent: true },
    })

    const message = await settingsApi.sendTenantTestEmail('test@example.com')
    expect(message).toContain('بنجاح')
    expect(apiPost).toHaveBeenCalledWith('/api/v1/tenant-settings/test-email', {
      email: 'test@example.com',
    })
  })
})
