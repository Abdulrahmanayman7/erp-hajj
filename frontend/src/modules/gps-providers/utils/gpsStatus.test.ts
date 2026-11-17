import { describe, expect, it } from 'vitest'

import type { GpsProvider } from '../types/gpsProviders'
import { isProviderVerified, providerLifecycleLabel } from './gpsStatus'

function provider(partial: Partial<GpsProvider>): GpsProvider {
  return {
    id: 1,
    name: 'P',
    code: 'p',
    connection_method: 'webhook',
    is_enabled: false,
    settings: {},
    has_credentials: false,
    credentials_masked: null,
    poll_interval_seconds: null,
    last_sync_at: null,
    last_health_status: null,
    last_error_sanitized: null,
    created_at: null,
    updated_at: null,
    ...partial,
  }
}

describe('gpsStatus helpers', () => {
  it('never treats enabled-only providers as verified', () => {
    expect(isProviderVerified(provider({ is_enabled: true, last_health_status: 'unknown' }))).toBe(
      false,
    )
    expect(isProviderVerified(provider({ is_enabled: true, last_health_status: 'healthy' }))).toBe(
      true,
    )
  })

  it('labels simulator as demo lifecycle', () => {
    expect(providerLifecycleLabel(provider({ connection_method: 'simulator' })).key).toBe(
      'gpsProviders.lifecycle.demo',
    )
  })

  it('labels healthy providers as verified', () => {
    expect(
      providerLifecycleLabel(provider({ is_enabled: true, last_health_status: 'healthy' })).key,
    ).toBe('gpsProviders.lifecycle.verified')
  })
})
