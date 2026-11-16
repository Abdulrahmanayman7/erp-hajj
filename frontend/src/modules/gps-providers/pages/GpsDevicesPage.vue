<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import {
  createGpsDeviceBinding,
  deleteGpsDeviceBinding,
  listGpsDeviceBindings,
  listGpsProviders,
} from '../api/gpsProvidersApi'

const { t } = useI18n()
const { can } = usePermissions()
const queryClient = useQueryClient()

const canView = computed(
  () => can('gps.devices.manage') || can('gps.providers.view') || can('gps.providers.manage'),
)
const canManage = computed(() => can('gps.devices.manage'))

const search = ref('')
const activeFilter = ref<'all' | '1' | '0'>('all')
const providerFilter = ref<number | ''>('')
const flash = ref<string | null>(null)

const deviceId = ref('')
const externalDeviceId = ref('')
const vehicleId = ref('')
const linkProviderId = ref<number | ''>('')

const providersQuery = useQuery({
  queryKey: ['gps-providers'],
  queryFn: listGpsProviders,
  enabled: canView,
})

const bindingsQuery = useQuery({
  queryKey: computed(() => [
    'gps-device-bindings',
    search.value,
    activeFilter.value,
    providerFilter.value,
  ]),
  queryFn: () =>
    listGpsDeviceBindings({
      search: search.value.trim() || undefined,
      isActive: activeFilter.value === 'all' ? undefined : activeFilter.value === '1',
      providerId: providerFilter.value === '' ? undefined : Number(providerFilter.value),
    }),
  enabled: canView,
})

const providerOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('common.all') },
  ...(providersQuery.data.value ?? []).map((row) => ({
    value: row.id,
    label: `${row.name} (${row.code})`,
  })),
])

const activeOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('common.all') },
  { value: '1', label: t('gpsDevices.activeOnly') },
  { value: '0', label: t('gpsDevices.inactiveOnly') },
])

const bindMutation = useMutation({
  mutationFn: () =>
    createGpsDeviceBinding({
      device_id: deviceId.value.trim(),
      external_device_id: externalDeviceId.value.trim() || deviceId.value.trim(),
      transport_season_vehicle_id: Number(vehicleId.value),
      gps_provider_id: linkProviderId.value === '' ? null : Number(linkProviderId.value),
      is_active: true,
    }),
  onSuccess: async () => {
    deviceId.value = ''
    externalDeviceId.value = ''
    vehicleId.value = ''
    flash.value = t('gpsProviders.deviceLinked')
    await queryClient.invalidateQueries({ queryKey: ['gps-device-bindings'] })
  },
})

const unbindMutation = useMutation({
  mutationFn: (id: number) => deleteGpsDeviceBinding(id),
  onSuccess: async () => {
    flash.value = t('gpsProviders.deviceUnlinked')
    await queryClient.invalidateQueries({ queryKey: ['gps-device-bindings'] })
  },
})

function freshnessLabel(row: NonNullable<typeof bindingsQuery.data.value>[number]): string {
  if (!row.last_known) return t('gpsDevices.noFix')
  if (row.last_known.is_simulated) return t('executiveDashboard.gpsMarkerStatus.simulated')
  return t(`executiveDashboard.gpsFreshness.${row.last_known.freshness_class}`)
}
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-6 p-4 md:p-6" dir="rtl">
    <AppPageHeader :title="t('gpsDevices.title')" :subtitle="t('gpsDevices.subtitle')" />

    <p v-if="flash" class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ flash }}</p>

    <div class="grid gap-3 md:grid-cols-3">
      <label class="block text-sm">
        <span class="mb-1 block text-brand-text-muted">{{ t('gpsDevices.search') }}</span>
        <input v-model="search" class="w-full rounded-md border px-2 py-1.5" data-test="gps-devices-search" />
      </label>
      <div>
        <p class="mb-1 text-sm text-brand-text-muted">{{ t('gpsDevices.providerFilter') }}</p>
        <AppSelect v-model="providerFilter" :options="providerOptions" />
      </div>
      <div>
        <p class="mb-1 text-sm text-brand-text-muted">{{ t('gpsDevices.activeFilter') }}</p>
        <AppSelect v-model="activeFilter" :options="activeOptions" />
      </div>
    </div>

    <div v-if="bindingsQuery.isLoading.value" class="text-sm text-brand-text-muted">
      {{ t('gpsDevices.loading') }}
    </div>
    <div v-else-if="bindingsQuery.isError.value" class="text-sm text-red-700">
      {{ t('gpsDevices.loadError') }}
    </div>
    <div v-else class="overflow-x-auto rounded-lg border border-[var(--border-subtle)] bg-white">
      <table class="min-w-full text-sm" data-test="gps-devices-table">
        <thead class="bg-[var(--surface-subtle)] text-start text-xs text-brand-text-muted">
          <tr>
            <th class="px-3 py-2 font-semibold">{{ t('gpsProviders.deviceId') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('gpsDevices.vehicle') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('gpsProviders.method') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('gpsDevices.lastFix') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('gpsDevices.health') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('gpsDevices.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in bindingsQuery.data.value ?? []"
            :key="row.id"
            class="border-t border-[var(--border-subtle)]"
          >
            <td class="px-3 py-2">
              <div class="font-semibold">{{ row.device_id }}</div>
              <div class="text-xs text-brand-text-muted">{{ row.external_device_id }}</div>
            </td>
            <td class="px-3 py-2">
              <div>{{ row.plate_number || row.vehicle_number || `#${row.transport_season_vehicle_id}` }}</div>
              <div class="text-xs text-brand-text-muted">{{ row.vehicle_class || '—' }}</div>
            </td>
            <td class="px-3 py-2">
              <div>{{ row.provider_name || '—' }}</div>
              <div class="text-xs text-brand-text-muted">{{ row.provider_code || '—' }}</div>
            </td>
            <td class="px-3 py-2">
              <div>{{ freshnessLabel(row) }}</div>
              <div class="text-xs text-brand-text-muted">{{ row.last_known?.recorded_at || '—' }}</div>
            </td>
            <td class="px-3 py-2">
              <span>{{ row.provider_health || '—' }}</span>
              <span v-if="!row.is_active" class="ms-2 text-xs text-amber-700">{{ t('gpsDevices.inactive') }}</span>
            </td>
            <td class="px-3 py-2">
              <button
                v-if="canManage"
                type="button"
                class="rounded-md border px-2 py-1 text-xs"
                @click="unbindMutation.mutate(row.id)"
              >
                {{ t('gpsProviders.unlink') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <p
        v-if="!(bindingsQuery.data.value?.length)"
        class="px-3 py-4 text-sm text-brand-text-muted"
        data-test="gps-devices-empty"
      >
        {{ t('gpsDevices.empty') }}
      </p>
    </div>

    <section v-if="canManage" class="space-y-3 rounded-lg border border-[var(--border-subtle)] bg-white p-4">
      <h2 class="text-sm font-bold">{{ t('gpsDevices.linkTitle') }}</h2>
      <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-4">
        <label class="text-sm">
          <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.deviceId') }}</span>
          <input v-model="deviceId" class="w-full rounded-md border px-2 py-1.5" data-test="gps-devices-device-id" />
        </label>
        <label class="text-sm">
          <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.externalDeviceId') }}</span>
          <input v-model="externalDeviceId" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label class="text-sm">
          <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.vehicleId') }}</span>
          <input v-model="vehicleId" class="w-full rounded-md border px-2 py-1.5" data-test="gps-devices-vehicle-id" />
        </label>
        <div>
          <p class="mb-1 text-sm text-brand-text-muted">{{ t('gpsDevices.providerFilter') }}</p>
          <AppSelect v-model="linkProviderId" :options="providerOptions" />
        </div>
      </div>
      <button
        type="button"
        class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
        :disabled="bindMutation.isPending.value"
        data-test="gps-devices-link"
        @click="bindMutation.mutate()"
      >
        {{ t('gpsProviders.linkDevice') }}
      </button>
    </section>
  </div>
</template>
