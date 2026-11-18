<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2 } from 'lucide-vue-next'

import { listSeasonVehicles } from '@/modules/transportation/api/seasonFleetApi'
import type { TransportSeasonVehicle } from '@/modules/transportation/types/fleet'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useFormDirty } from '@/shared/composables/useFormDirty'

export interface GpsDeviceLinkFormState {
  device_id: string
  external_device_id: string
  season_id: number | null
  transport_season_vehicle_id: number | null
  gps_provider_id: number | ''
}

const props = defineProps<{
  open: boolean
  form: GpsDeviceLinkFormState
  providerOptions: AppSelectOption[]
  submitting: boolean
  formError: string | null
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [value: GpsDeviceLinkFormState]
}>()

const { t } = useI18n()
const drawerRef = ref<InstanceType<typeof AppFormDrawer> | null>(null)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasonOptions = computed<AppSelectOption[]>(() =>
  (seasonsQuery.data.value?.data ?? []).map((row) => ({
    value: row.id,
    label: `${row.name} (${row.status})`,
  })),
)

const canSubmit = computed(
  () =>
    props.form.device_id.trim().length > 0 &&
    props.form.season_id != null &&
    props.form.transport_season_vehicle_id != null,
)

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)

function patch(partial: Partial<GpsDeviceLinkFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

const fetchFleet = (p: { search?: string; page: number; per_page: number }) =>
  listSeasonVehicles({
    ...p,
    is_active: true,
    seasonId: props.form.season_id,
  })

const mapFleet = (row: TransportSeasonVehicle): AppSelectOption => ({
  value: row.id,
  label: row.vehicle?.plate_number || row.vehicle?.vehicle_number || `#${row.id}`,
  hint: [
    row.vehicle?.vehicle_class,
    row.vehicle?.vehicle_number,
    row.is_active ? null : t('gpsDevices.inactive'),
  ]
    .filter(Boolean)
    .join(' · '),
})

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}

function requestDismiss(): void {
  void drawerRef.value?.requestClose()
}

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      resetDirty()
      return
    }
    if (props.form.season_id == null) {
      const rows = seasonsQuery.data.value?.data ?? []
      const active = rows.find((row) => row.status === 'active') ?? rows[0]
      if (active) patch({ season_id: active.id })
    }
    queueMicrotask(() => capture())
  },
)

watch(
  () => props.form.season_id,
  () => {
    if (props.open) patch({ transport_season_vehicle_id: null })
  },
)
</script>

<template>
  <AppFormDrawer
    ref="drawerRef"
    :open="open"
    :title="t('gpsDevices.linkTitle')"
    :description="t('gpsDevices.linkSubtitle')"
    size="sm"
    :dirty="dirty"
    :submitting="submitting"
    :submit-disabled="!canSubmit"
    hide-default-footer
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <div data-test="gps-device-link-drawer" class="contents">
      <p v-if="formError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
        {{ formError }}
      </p>

      <label class="app-field">
        <span class="app-field__label">{{ t('gpsProviders.deviceId') }}</span>
        <input
          :value="form.device_id"
          class="app-field__control app-field__control--input"
          data-test="gps-devices-device-id"
          :placeholder="t('gpsDevices.deviceIdExample')"
          @input="patch({ device_id: ($event.target as HTMLInputElement).value })"
        >
      </label>

      <label class="app-field">
        <span class="app-field__label">{{ t('gpsProviders.externalDeviceId') }}</span>
        <input
          :value="form.external_device_id"
          class="app-field__control app-field__control--input"
          :placeholder="t('gpsDevices.externalIdExample')"
          @input="patch({ external_device_id: ($event.target as HTMLInputElement).value })"
        >
        <span class="mt-1.5 block text-xs text-brand-text-muted">{{ t('gpsDevices.externalIdHint') }}</span>
      </label>

      <div class="app-field">
        <p class="app-field__label">{{ t('gpsDevices.providerFilter') }}</p>
        <AppSelect
          :model-value="form.gps_provider_id"
          :options="providerOptions"
          clearable
          @update:model-value="patch({ gps_provider_id: ($event as number | '') ?? '' })"
        />
      </div>

      <div class="app-field">
        <p class="app-field__label">{{ t('gpsMap.season') }}</p>
        <AppSelect
          :model-value="form.season_id"
          :options="seasonOptions"
          data-test="gps-devices-season"
          @update:model-value="patch({ season_id: ($event as number) ?? null, transport_season_vehicle_id: null })"
        />
      </div>

      <div class="app-field">
        <p class="app-field__label">{{ t('gpsDevices.vehicle') }}</p>
        <AppRemoteSelect
          :model-value="form.transport_season_vehicle_id"
          :query-key="['gps-device-link-fleet', form.season_id]"
          :fetcher="fetchFleet"
          :map-option="mapFleet"
          :enabled="form.season_id != null"
          :placeholder="t('gpsDevices.vehiclePlaceholder')"
          clearable
          data-test="gps-devices-vehicle-select"
          @update:model-value="patch({ transport_season_vehicle_id: ($event as number) ?? null })"
        />
        <span class="mt-1.5 block text-xs text-brand-text-muted">{{ t('gpsDevices.vehicleHint') }}</span>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold disabled:opacity-50 sm:min-w-[7.5rem]"
        :disabled="submitting"
        @click="requestDismiss"
      >
        {{ t('gpsProviders.cancel') }}
      </button>
      <button
        type="submit"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50 sm:min-w-[7.5rem]"
        :disabled="submitting || !canSubmit"
        data-test="gps-devices-link"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        {{ t('gpsProviders.linkDevice') }}
      </button>
    </template>
  </AppFormDrawer>
</template>