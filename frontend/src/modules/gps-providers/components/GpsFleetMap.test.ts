import { createApp, nextTick } from 'vue'
import { createI18n } from 'vue-i18n'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ar } from '@/locales/ar'
import { executiveDashboardAr } from '@/locales/executiveDashboardAr'
import GpsFleetMap from './GpsFleetMap.vue'

vi.mock('leaflet', () => {
  const map = {
    setView: vi.fn().mockReturnThis(),
    remove: vi.fn(),
    fitBounds: vi.fn(),
    invalidateSize: vi.fn(),
  }
  const layer = {
    clearLayers: vi.fn(),
    addTo: vi.fn(function addTo(this: unknown) {
      return this
    }),
  }
  const marker = {
    bindPopup: vi.fn().mockReturnThis(),
    addTo: vi.fn().mockReturnThis(),
  }
  return {
    default: {
      map: vi.fn(() => map),
      tileLayer: vi.fn(() => ({ addTo: vi.fn() })),
      layerGroup: vi.fn(() => layer),
      marker: vi.fn(() => marker),
      divIcon: vi.fn((opts: unknown) => opts),
      latLngBounds: vi.fn((points: unknown) => points),
    },
  }
})

vi.mock('leaflet/dist/leaflet.css', () => ({}))

describe('GpsFleetMap', () => {
  const apps: Array<ReturnType<typeof createApp>> = []

  afterEach(() => {
    apps.splice(0).forEach((app) => app.unmount())
  })

  it('shows DEMO banner for simulated markers', async () => {
    const host = document.createElement('div')
    document.body.appendChild(host)
    const i18n = createI18n({
      legacy: false,
      locale: 'ar',
      messages: {
        ar: {
          ...ar,
          executiveDashboard: executiveDashboardAr.executiveDashboard,
        },
      },
    })
    const app = createApp(GpsFleetMap, {
      markers: [
        {
          transport_season_vehicle_id: 1,
          lat: 21.42,
          lng: 39.82,
          freshness_class: 'unavailable',
          marker_status: 'simulated',
          recorded_at: '2026-11-16T10:00:00Z',
          is_simulated: true,
          vehicle_label: 'DEMO-1',
          vehicle_class: 'bus',
        },
      ],
    })
    app.use(i18n)
    app.mount(host)
    apps.push(app)
    await nextTick()

    expect(host.querySelector('[data-test="gps-map-demo-banner"]')).not.toBeNull()
    expect(host.querySelector('[data-test="gps-fleet-map"]')).not.toBeNull()
  })
})
