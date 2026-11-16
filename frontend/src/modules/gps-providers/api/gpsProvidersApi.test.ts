import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiGet, apiPost } from '@/shared/api/http'
import {
  createGpsProvider,
  fetchGpsFleetMap,
  listGpsDeviceBindings,
  listGpsProviders,
  testGpsProvider,
} from './gpsProvidersApi'

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

  it('lists device bindings with search filters', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: [{ id: 9, device_id: 'bus-1' }],
    } as never)

    const rows = await listGpsDeviceBindings({ search: 'bus', providerId: 3, isActive: true })
    expect(apiGet).toHaveBeenCalledWith(
      '/api/v1/gps/device-bindings?gps_provider_id=3&search=bus&is_active=1',
    )
    expect(rows[0]?.device_id).toBe('bus-1')
  })

  it('loads fleet map markers for a season', async () => {
    vi.mocked(apiGet).mockResolvedValue({
      success: true,
      message: '',
      data: {
        freshness_class: 'unavailable',
        markers: [{ transport_season_vehicle_id: 1, lat: 21.4, lng: 39.8, is_simulated: true }],
      },
    } as never)

    const panel = await fetchGpsFleetMap(12)
    expect(apiGet).toHaveBeenCalledWith('/api/v1/gps/fleet-map?season_id=12')
    expect(panel.markers[0]?.is_simulated).toBe(true)
  })
})
