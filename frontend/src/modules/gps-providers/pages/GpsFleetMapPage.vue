<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery } from '@tanstack/vue-query'
import { ChevronDown, ChevronUp, RefreshCw } from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useBreakpoint } from '@/shared/composables/useBreakpoint'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { fetchGpsFleetMap } from '../api/gpsProvidersApi'
import GpsFleetMap from '../components/GpsFleetMap.vue'
import type { GpsFleetMapMarker, GpsMarkerStatus } from '../types/gpsProviders'
import { gpsToneClass, markerStatusOf } from '../utils/gpsStatus'

const { t } = useI18n()
const { can } = usePermissions()
const canView = computed(() => can('gps.map.view'))

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasonId = ref<number | null>(null)
const refreshSeconds = ref(30)
const filterProvider = ref<string | null>('')
const filterStatus = ref<GpsMarkerStatus | 'all'>('all')
const filterVehicle = ref('')
const selectedVehicleId = ref<number | null>(null)
const { isMobile } = useBreakpoint()
const panelOpen = ref(true)

watch(
  isMobile,
  (mobile) => {
    if (mobile) panelOpen.value = false
  },
  { immediate: true },
)
const mapRef = ref<{ invalidateSize: () => void; focusVehicle: (id: number | null) => void } | null>(
  null,
)

const seasons = computed(() => seasonsQuery.data.value?.data ?? [])

watch(
  seasons,
  (rows) => {
    if (!rows.length || seasonId.value != null) return
    const active = rows.find((row) => row.status === 'active') ?? rows[0]
    if (active) seasonId.value = active.id
  },
  { immediate: true },
)

const seasonOptions = computed<AppSelectOption[]>(() =>
  seasons.value.map((row) => ({
    value: row.id,
    label: `${row.name} (${row.status})`,
  })),
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('common.all') },
  { value: 'online', label: t('executiveDashboard.gpsMarkerStatus.online') },
  { value: 'stale', label: t('executiveDashboard.gpsMarkerStatus.stale') },
  { value: 'offline', label: t('executiveDashboard.gpsMarkerStatus.offline') },
  { value: 'simulated', label: t('executiveDashboard.gpsMarkerStatus.simulated') },
  { value: 'no_device', label: t('executiveDashboard.gpsMarkerStatus.no_device') },
  {
    value: 'provider_unavailable',
    label: t('executiveDashboard.gpsMarkerStatus.provider_unavailable'),
  },
])

const mapQuery = useQuery({
  queryKey: computed(() => ['gps-fleet-map', seasonId.value]),
  queryFn: () => {
    if (seasonId.value == null) throw new Error('season required')
    return fetchGpsFleetMap(seasonId.value)
  },
  enabled: computed(() => canView.value && seasonId.value != null),
  refetchInterval: computed(() => Math.max(10, Number(refreshSeconds.value) || 30) * 1000),
})

const markers = computed(() => mapQuery.data.value?.markers ?? [])

const providerOptions = computed<AppSelectOption[]>(() => {
  const codes = new Set<string>()
  for (const marker of markers.value) {
    if (marker.provider_code) codes.add(marker.provider_code)
  }
  return [
    { value: '', label: t('common.all') },
    ...[...codes].sort().map((code) => ({ value: code, label: code })),
  ]
})

const panelMarkers = computed(() => {
  const vehicleQ = filterVehicle.value.trim().toLowerCase()
  return markers.value.filter((marker) => {
    if (filterProvider.value && marker.provider_code !== filterProvider.value) return false
    if (filterStatus.value !== 'all') {
      if (markerStatusOf(marker) !== filterStatus.value) return false
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
    return true
  })
})

const kpis = computed(() => {
  const rows = markers.value
  return {
    total: rows.length,
    withPosition: rows.filter((m) => m.lat != null && m.lng != null).length,
    real: rows.filter((m) => !m.is_simulated && m.freshness_class === 'real').length,
    simulated: rows.filter((m) => m.is_simulated).length,
    stale: rows.filter((m) => !m.is_simulated && m.freshness_class === 'stale').length,
  }
})

function statusLabel(marker: GpsFleetMapMarker): string {
  if (marker.is_simulated) return t('executiveDashboard.gpsMarkerStatus.simulated')
  if (marker.marker_status) return t(`executiveDashboard.gpsMarkerStatus.${marker.marker_status}`)
  return t(`executiveDashboard.gpsFreshness.${marker.freshness_class}`)
}

function statusTone(marker: GpsFleetMapMarker): keyof typeof gpsToneClass {
  if (marker.is_simulated) return 'warning'
  if (marker.freshness_class === 'real' || marker.marker_status === 'online') return 'success'
  if (marker.freshness_class === 'stale' || marker.marker_status === 'stale') return 'warning'
  if (marker.lat == null || marker.lng == null) return 'neutral'
  return 'danger'
}

async function selectVehicle(id: number) {
  selectedVehicleId.value = id
  await nextTick()
  mapRef.value?.focusVehicle(id)
}

async function togglePanel() {
  panelOpen.value = !panelOpen.value
  await nextTick()
  mapRef.value?.invalidateSize()
}
</script>

<template>
  <div class="mx-auto flex max-w-[1600px] flex-col gap-3 p-3 md:p-4" dir="rtl">
    <AppPageHeader :title="t('gpsMap.title')" :subtitle="t('gpsMap.subtitle')">
      <template #actions>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-white px-3 text-sm font-semibold"
          :disabled="mapQuery.isFetching.value"
          @click="mapQuery.refetch()"
        >
          <RefreshCw class="h-4 w-4" :class="mapQuery.isFetching.value ? 'animate-spin' : ''" />
          {{ t('gpsProviders.refresh') }}
        </button>
      </template>
    </AppPageHeader>

    <p v-if="!canView" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
      {{ t('gpsMap.permissionDenied') }}
    </p>

    <template v-else>
      <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-brand-border bg-white px-3 py-2">
          <p class="text-[11px] font-semibold text-brand-text-muted">{{ t('gpsMap.kpiFleet') }}</p>
          <p class="text-xl font-bold">{{ kpis.total }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white px-3 py-2">
          <p class="text-[11px] font-semibold text-brand-text-muted">{{ t('gpsMap.kpiWithPosition') }}</p>
          <p class="text-xl font-bold">{{ kpis.withPosition }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white px-3 py-2">
          <p class="text-[11px] font-semibold text-brand-text-muted">{{ t('gpsMap.kpiReal') }}</p>
          <p class="text-xl font-bold text-emerald-800">{{ kpis.real }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white px-3 py-2">
          <p class="text-[11px] font-semibold text-brand-text-muted">{{ t('gpsMap.kpiStale') }}</p>
          <p class="text-xl font-bold text-amber-800">{{ kpis.stale }}</p>
        </div>
        <div class="rounded-xl border border-brand-border bg-white px-3 py-2">
          <p class="text-[11px] font-semibold text-brand-text-muted">{{ t('gpsMap.kpiDemo') }}</p>
          <p class="text-xl font-bold text-amber-900">{{ kpis.simulated }}</p>
        </div>
      </div>

      <div class="grid gap-2 rounded-xl border border-brand-border bg-white p-3 md:grid-cols-2 lg:grid-cols-5">
        <div>
          <p class="mb-1 text-xs text-brand-text-muted">{{ t('gpsMap.season') }}</p>
          <AppSelect v-model="seasonId" :options="seasonOptions" />
        </div>
        <label class="block text-sm">
          <span class="mb-1 block text-xs text-brand-text-muted">{{ t('gpsMap.refreshSeconds') }}</span>
          <input
            v-model.number="refreshSeconds"
            type="number"
            min="10"
            max="300"
            class="w-full rounded-xl border px-3 py-2 text-sm"
            data-test="gps-map-refresh"
          />
        </label>
        <div>
          <p class="mb-1 text-xs text-brand-text-muted">{{ t('gpsMap.filterProvider') }}</p>
          <AppSelect v-model="filterProvider" :options="providerOptions" />
        </div>
        <div>
          <p class="mb-1 text-xs text-brand-text-muted">{{ t('gpsMap.filterStatus') }}</p>
          <AppSelect v-model="filterStatus" :options="statusOptions" />
        </div>
        <label class="block text-sm lg:col-span-1 md:col-span-2">
          <span class="mb-1 block text-xs text-brand-text-muted">{{ t('gpsMap.filterVehicle') }}</span>
          <input
            v-model="filterVehicle"
            class="w-full rounded-xl border px-3 py-2 text-sm"
            data-test="gps-map-vehicle-filter"
          />
        </label>
      </div>

      <div v-if="mapQuery.isLoading.value" class="h-[60vh] animate-pulse rounded-xl bg-[var(--surface-subtle)]" />
      <div v-else-if="mapQuery.isError.value" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p>{{ t('gpsMap.loadError') }}</p>
        <button type="button" class="mt-2 font-semibold underline" @click="mapQuery.refetch()">
          {{ t('gpsProviders.retry') }}
        </button>
      </div>
      <div
        v-else
        class="grid min-h-0 gap-3 lg:grid-cols-[minmax(0,1fr)_20rem]"
        data-test="gps-map-control-center"
      >
        <div class="min-w-0">
          <GpsFleetMap
            ref="mapRef"
            :markers="markers"
            :filter-provider="filterProvider || null"
            :filter-status="filterStatus"
            :filter-vehicle="filterVehicle"
            :selected-vehicle-id="selectedVehicleId"
            height-class="h-[42vh] min-h-[16rem] max-h-[50dvh] sm:h-[50vh] sm:min-h-[18rem] sm:max-h-none lg:h-[66vh] lg:max-h-none"
            @select="selectVehicle"
          />
        </div>

        <aside class="flex min-h-0 flex-col rounded-xl border border-brand-border bg-white lg:max-h-[66vh]">
          <div class="flex items-center justify-between gap-2 border-b border-brand-border px-3 py-2.5">
            <div>
              <h2 class="text-sm font-bold">{{ t('gpsMap.vehiclePanel') }}</h2>
              <p class="text-[11px] text-brand-text-muted">{{ panelMarkers.length }} {{ t('gpsMap.vehiclesCount') }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg border px-2 py-1 text-xs font-semibold lg:hidden"
              @click="togglePanel"
            >
              <component :is="panelOpen ? ChevronUp : ChevronDown" class="h-4 w-4" />
            </button>
          </div>
          <div
            v-show="panelOpen"
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2"
            data-test="gps-map-vehicle-list"
          >
            <button
              v-for="marker in panelMarkers"
              :key="marker.transport_season_vehicle_id"
              type="button"
              class="mb-1.5 w-full rounded-xl border px-3 py-2 text-start transition"
              :class="
                selectedVehicleId === marker.transport_season_vehicle_id
                  ? 'border-brand-primary bg-emerald-50/60'
                  : 'border-transparent hover:bg-[var(--surface-subtle)]'
              "
              @click="selectVehicle(marker.transport_season_vehicle_id)"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold">
                    {{ marker.vehicle_label || marker.plate_number || `#${marker.transport_season_vehicle_id}` }}
                  </p>
                  <p class="truncate text-[11px] text-brand-text-muted">
                    {{ marker.provider_code || '—' }}
                    ·
                    {{ marker.recorded_at || t('gpsDevices.noFix') }}
                  </p>
                </div>
                <span
                  class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                  :class="gpsToneClass[statusTone(marker)]"
                >
                  {{ statusLabel(marker) }}
                </span>
              </div>
              <p
                v-if="marker.lat == null || marker.lng == null"
                class="mt-1 text-[11px] text-brand-text-muted"
              >
                {{ t('gpsMap.noPositionHint') }}
              </p>
            </button>
            <p
              v-if="panelMarkers.length === 0"
              class="px-2 py-6 text-center text-xs text-brand-text-muted"
            >
              {{ t('gpsMap.noVehicles') }}
            </p>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
