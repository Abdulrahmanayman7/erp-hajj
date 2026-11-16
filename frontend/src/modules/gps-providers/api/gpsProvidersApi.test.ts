import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiGet, apiPost } from '@/shared/api/http'
import { createGpsProvider, listGpsProviders, testGpsProvider } from './gpsProvidersApi'

vi.mock('@/shared/api/http', () => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
  apiPatch: vi.fn(),
  apiDelete: vi.fn(),
}))

describe('gpsProvidersApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lists providers from the hub API', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 1, name: 'Traccar UAT', code: 'traccar-uat' }],
    } as never)

    const rows = await listGpsProviders()
    expect(apiGet).toHaveBeenCalledWith('/api/v1/gps/providers')
    expect(rows[0]?.code).toBe('traccar-uat')
  })

  it('creates a provider without echoing secrets', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: {
        id: 2,
        name: 'Webhook',
        code: 'wh-1',
        has_credentials: true,
        credentials_masked: '********',
      },
    } as never)

    const row = await createGpsProvider({
      name: 'Webhook',
      code: 'wh-1',
      connection_method: 'webhook',
      credentials: { webhook_secret: 'super-secret' },
    })
    expect(row.credentials_masked).toBe('********')
    expect(JSON.stringify(row)).not.toContain('super-secret')
  })

  it('posts provider connection tests', async () => {
    vi.mocked(apiPost).mockResolvedValue({
      success: true,
      message: '',
      data: {
        status: 'blocked',
        message: 'G4 Live outbound is BLOCKED',
        live_contact_made: false,
        provider: { id: 1 },
      },
    } as never)

    const result = await testGpsProvider(1)
    expect(apiPost).toHaveBeenCalledWith('/api/v1/gps/providers/1/test')
    expect(result.live_contact_made).toBe(false)
  })
})
