import type {
  GpsConnectionMethod,
  GpsDeviceBinding,
  GpsFreshnessClass,
  GpsMarkerStatus,
  GpsProvider,
  GpsProviderHealthStatus,
} from '../types/gpsProviders'

/** Verified only when the API reports a healthy connectivity check — never from enabled alone. */
export function isProviderVerified(provider: GpsProvider): boolean {
  return provider.last_health_status === 'healthy'
}

export function providerMethodLabelKey(method: GpsConnectionMethod): string {
  return `gpsProviders.methods.${method}`
}

export function providerHealthLabelKey(
  status: GpsProviderHealthStatus | null | undefined,
): string {
  return `gpsProviders.health.${status ?? 'unknown'}`
}

export function providerLifecycleLabel(provider: GpsProvider): {
  key: string
  tone: 'neutral' | 'success' | 'warning' | 'danger' | 'info'
} {
  if (provider.connection_method === 'simulator') {
    return { key: 'gpsProviders.lifecycle.demo', tone: 'warning' }
  }
  if (isProviderVerified(provider)) {
    return { key: 'gpsProviders.lifecycle.verified', tone: 'success' }
  }
  if (provider.is_enabled) {
    return { key: 'gpsProviders.lifecycle.enabled', tone: 'info' }
  }
  if (provider.has_credentials || Object.keys(provider.settings ?? {}).length > 0) {
    return { key: 'gpsProviders.lifecycle.configured', tone: 'neutral' }
  }
  return { key: 'gpsProviders.lifecycle.draft', tone: 'neutral' }
}

export function bindingFreshnessTone(
  binding: GpsDeviceBinding,
): 'success' | 'warning' | 'danger' | 'neutral' {
  if (!binding.last_known) return 'neutral'
  if (binding.last_known.is_simulated) return 'warning'
  if (binding.last_known.freshness_class === 'real') return 'success'
  if (binding.last_known.freshness_class === 'stale') return 'warning'
  return 'neutral'
}

export function markerStatusOf(
  marker: {
    marker_status?: GpsMarkerStatus
    is_simulated?: boolean
    freshness_class: GpsFreshnessClass
  },
): GpsMarkerStatus | GpsFreshnessClass {
  if (marker.marker_status) return marker.marker_status
  if (marker.is_simulated) return 'simulated'
  return marker.freshness_class
}

export const gpsToneClass: Record<
  'neutral' | 'success' | 'warning' | 'danger' | 'info',
  string
> = {
  neutral: 'border-[var(--border-subtle)] bg-[var(--surface-subtle)] text-brand-text',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
  danger: 'border-red-200 bg-red-50 text-red-800',
  info: 'border-sky-200 bg-sky-50 text-sky-900',
}
