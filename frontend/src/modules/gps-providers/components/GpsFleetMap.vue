<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { GpsFleetMapMarker, GpsMarkerStatus } from '../types/gpsProviders'

const props = withDefaults(
  defineProps<{
    markers: GpsFleetMapMarker[]
    heightClass?: string
    filterProvider?: string | null
    filterStatus?: GpsMarkerStatus | 'all' | null
    filterVehicle?: string
  }>(),
  {
    heightClass: 'h-[28rem]',
    filterProvider: null,
    filterStatus: 'all',
    filterVehicle: '',
  },
)

const { t } = useI18n()
const mapEl = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let layer: L.LayerGroup | null = null

const filteredMarkers = computed(() => {
  const vehicleQ = props.filterVehicle.trim().toLowerCase()
  return props.markers.filter((marker) => {
    if (props.filterProvider && marker.provider_code !== props.filterProvider) return false
    if (props.filterStatus && props.filterStatus !== 'all') {
      const status = marker.marker_status ?? (marker.is_simulated ? 'simulated' : marker.freshness_class)
      if (status !== props.filterStatus) return false
    }
    if (vehicleQ) {
      const hay = [
        marker.vehicle_label,
        marker.vehicle_number,
        marker.plate_number,
        marker.device_id,
        String(marker.transport_season_vehicle_id),
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      if (!hay.includes(vehicleQ)) return false
    }
    return marker.lat != null && marker.lng != null
  })
})

const hasSimulated = computed(() => props.markers.some((m) => m.is_simulated))

function statusColor(marker: GpsFleetMapMarker): string {
  const status = marker.marker_status
  if (status === 'simulated' || marker.is_simulated) return '#b45309'
  if (status === 'online' || marker.freshness_class === 'real') return '#047857'
  if (status === 'stale' || marker.freshness_class === 'stale') return '#ca8a04'
  return '#6b7280'
}

function classGlyph(vehicleClass: string | null | undefined): string {
  if (vehicleClass === 'bus') return 'B'
  if (vehicleClass === 'minibus') return 'M'
  if (vehicleClass === 'car') return 'C'
  return 'V'
}

function markerIcon(marker: GpsFleetMapMarker): L.DivIcon {
  const color = statusColor(marker)
  const glyph = classGlyph(marker.vehicle_class)
  const demo = marker.is_simulated ? ' DEMO' : ''
  return L.divIcon({
    className: 'gps-fleet-divicon',
    html: `<div style="display:flex;flex-direction:column;align-items:center;gap:2px">
      <div style="width:28px;height:28px;border-radius:9999px;background:${color};color:#fff;display:flex;align-items:center;justify-content:center;font:700 12px/1 sans-serif;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)">${glyph}</div>
      <span style="background:rgba(255,255,255,.92);padding:1px 4px;border-radius:4px;font:600 10px/1.2 sans-serif;color:#111;white-space:nowrap">${escapeHtml(marker.vehicle_label || `#${marker.transport_season_vehicle_id}`)}${demo}</span>
    </div>`,
    iconSize: [88, 44],
    iconAnchor: [44, 22],
  })
}

function escapeHtml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
}

function popupHtml(marker: GpsFleetMapMarker): string {
  const status = marker.marker_status
    ? t(`executiveDashboard.gpsMarkerStatus.${marker.marker_status}`)
    : t(`executiveDashboard.gpsFreshness.${marker.freshness_class}`)
  const lines = [
    `<strong>${escapeHtml(marker.vehicle_label || `#${marker.transport_season_vehicle_id}`)}</strong>`,
    marker.plate_number ? `لوحة: ${escapeHtml(marker.plate_number)}` : null,
    marker.vehicle_number ? `رقم: ${escapeHtml(marker.vehicle_number)}` : null,
    marker.vehicle_class ? `فئة: ${escapeHtml(marker.vehicle_class)}` : null,
    `الحالة: ${escapeHtml(status)}`,
    marker.recorded_at ? `آخر تحديث: ${escapeHtml(marker.recorded_at)}` : null,
    marker.speed_kph != null ? `السرعة: ${marker.speed_kph} كم/س` : null,
    marker.heading_deg != null ? `الاتجاه: ${marker.heading_deg}°` : null,
    marker.provider_code ? `المزود: ${escapeHtml(marker.provider_code)}` : null,
    marker.is_simulated ? `<em>${escapeHtml(t('executiveDashboard.gpsSimulatedBanner'))}</em>` : null,
  ].filter(Boolean)
  return lines.join('<br/>')
}

function ensureMap(): void {
  if (!mapEl.value || map) return
  map = L.map(mapEl.value, {
    zoomControl: true,
    attributionControl: true,
  }).setView([21.4225, 39.8262], 12)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map)

  layer = L.layerGroup().addTo(map)
}

function renderMarkers(): void {
  if (!map || !layer) return
  layer.clearLayers()
  const bounds: L.LatLngExpression[] = []

  for (const marker of filteredMarkers.value) {
    if (marker.lat == null || marker.lng == null) continue
    const point: L.LatLngExpression = [marker.lat, marker.lng]
    bounds.push(point)
    L.marker(point, { icon: markerIcon(marker) })
      .bindPopup(popupHtml(marker))
      .addTo(layer)
  }

  if (bounds.length === 1) {
    map.setView(bounds[0]!, 14)
  } else if (bounds.length > 1) {
    map.fitBounds(L.latLngBounds(bounds), { padding: [28, 28] })
  }
  map.invalidateSize()
}

onMounted(() => {
  ensureMap()
  renderMarkers()
})

watch(
  () => [filteredMarkers.value, props.markers],
  () => {
    ensureMap()
    renderMarkers()
  },
  { deep: true },
)

onBeforeUnmount(() => {
  map?.remove()
  map = null
  layer = null
})
</script>

<template>
  <div class="space-y-2" data-test="gps-fleet-map">
    <p
      v-if="hasSimulated"
      class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900"
      data-test="gps-map-demo-banner"
    >
      {{ t('executiveDashboard.gpsSimulatedBanner') }}
    </p>
    <div
      ref="mapEl"
      class="w-full overflow-hidden rounded-lg border border-[var(--border-subtle)]"
      :class="heightClass"
      role="application"
      :aria-label="t('gpsMap.mapAria')"
    />
    <p v-if="filteredMarkers.length === 0" class="text-xs text-brand-text-muted" data-test="gps-map-empty">
      {{ t('gpsMap.noPlottable') }}
    </p>
  </div>
</template>

<style scoped>
:deep(.gps-fleet-divicon) {
  background: transparent;
  border: 0;
}
</style>
