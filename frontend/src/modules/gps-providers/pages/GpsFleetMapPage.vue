<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery } from '@tanstack/vue-query'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { fetchGpsFleetMap } from '../api/gpsProvidersApi'
import GpsFleetMap from '../components/GpsFleetMap.vue'
import type { GpsMarkerStatus } from '../types/gpsProviders'

const { t } = useI18n()
const { can } = usePermissions()
const canView = computed(() => can('gps.map.view'))

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasonId = ref<number | null>(null)
const refreshSeconds = ref(30)
const filterProvider = ref<string | null>('')
const filterStatus = ref<GpsMarkerStatus | 'all'>('all')
const filterVehicle = ref('')

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

const providerOptions = computed<AppSelectOption[]>(() => {
  const codes = new Set<string>()
  for (const marker of mapQuery.data.value?.markers ?? []) {
    if (marker.provider_code) codes.add(marker.provider_code)
  }
  return [
    { value: '', label: t('common.all') },
    ...[...codes].sort().map((code) => ({ value: code, label: code })),
  ]
})
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-4 p-4 md:p-6" dir="rtl">
    <AppPageHeader :title="t('gpsMap.title')" :subtitle="t('gpsMap.subtitle')" />

    <p v-if="!canView" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
      {{ t('gpsMap.permissionDenied') }}
    </p>

    <template v-else>
      <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
        <div>
          <p class="mb-1 text-sm text-brand-text-muted">{{ t('gpsMap.season') }}</p>
          <AppSelect v-model="seasonId" :options="seasonOptions" />
        </div>
        <label class="block text-sm">
          <span class="mb-1 block text-brand-text-muted">{{ t('gpsMap.refreshSeconds') }}</span>
          <input
            v-model.number="refreshSeconds"
            type="number"
            min="10"
            max="300"
            class="w-full rounded-md border px-2 py-1.5"
            data-test="gps-map-refresh"
          />
        </label>
        <div>
          <p class="mb-1 text-sm text-brand-text-muted">{{ t('gpsMap.filterProvider') }}</p>
          <AppSelect v-model="filterProvider" :options="providerOptions" />
        </div>
        <div>
          <p class="mb-1 text-sm text-brand-text-muted">{{ t('gpsMap.filterStatus') }}</p>
          <AppSelect v-model="filterStatus" :options="statusOptions" />
        </div>
      </div>
      <label class="block text-sm">
        <span class="mb-1 block text-brand-text-muted">{{ t('gpsMap.filterVehicle') }}</span>
        <input
          v-model="filterVehicle"
          class="w-full rounded-md border px-2 py-1.5"
          data-test="gps-map-vehicle-filter"
        />
      </label>

      <div v-if="mapQuery.isLoading.value" class="text-sm text-brand-text-muted">{{ t('gpsMap.loading') }}</div>
      <div v-else-if="mapQuery.isError.value" class="text-sm text-red-700">{{ t('gpsMap.loadError') }}</div>
      <GpsFleetMap
        v-else-if="mapQuery.data.value"
        :markers="mapQuery.data.value.markers"
        :filter-provider="filterProvider || null"
        :filter-status="filterStatus"
        :filter-vehicle="filterVehicle"
        height-class="h-[32rem] md:h-[36rem]"
      />
    </template>
  </div>
</template>
