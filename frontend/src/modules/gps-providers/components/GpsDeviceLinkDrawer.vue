<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { listSeasonVehicles } from '@/modules/transportation/api/seasonFleetApi'
import type { TransportSeasonVehicle } from '@/modules/transportation/types/fleet'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'

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
const deviceInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

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

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !props.submitting) {
    event.preventDefault()
    emit('close')
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown)
      if (props.form.season_id == null) {
        const rows = seasonsQuery.data.value?.data ?? []
        const active = rows.find((row) => row.status === 'active') ?? rows[0]
        if (active) patch({ season_id: active.id })
      }
      await nextTick()
      deviceInputRef.value?.focus()
      return
    }
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    previouslyFocused?.focus?.()
    previouslyFocused = null
  },
)

watch(
  () => props.form.season_id,
  () => {
    if (props.open) patch({ transport_season_vehicle_id: null })
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="gps-drawer">
      <div v-if="open" class="fixed inset-0 z-50" role="presentation" data-test="gps-device-link-drawer">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="!submitting && emit('close')"
        />
        <aside
          class="gps-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[480px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="t('gpsDevices.linkTitle')"
          @click.stop
        >
          <header class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border px-4 py-4 sm:px-6">
            <div>
              <h2 class="text-lg font-bold text-brand-text">{{ t('gpsDevices.linkTitle') }}</h2>
              <p class="mt-1 text-xs text-brand-text-muted">{{ t('gpsDevices.linkSubtitle') }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-brand-text-muted hover:bg-[var(--surface-subtle)]"
              :disabled="submitting"
              :aria-label="t('gpsProviders.cancel')"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-6">
            <p v-if="formError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
              {{ formError }}
            </p>

            <label class="block text-sm">
              <span class="mb-1 block font-semibold">{{ t('gpsProviders.deviceId') }}</span>
              <input
                ref="deviceInputRef"
                :value="form.device_id"
                class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                data-test="gps-devices-device-id"
                :placeholder="t('gpsDevices.deviceIdExample')"
                @input="patch({ device_id: ($event.target as HTMLInputElement).value })"
              />
            </label>

            <label class="block text-sm">
              <span class="mb-1 block font-semibold">{{ t('gpsProviders.externalDeviceId') }}</span>
              <input
                :value="form.external_device_id"
                class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                :placeholder="t('gpsDevices.externalIdExample')"
                @input="patch({ external_device_id: ($event.target as HTMLInputElement).value })"
              />
              <span class="mt-1 block text-xs text-brand-text-muted">{{ t('gpsDevices.externalIdHint') }}</span>
            </label>

            <div>
              <p class="mb-1 text-sm font-semibold">{{ t('gpsDevices.providerFilter') }}</p>
              <AppSelect
                :model-value="form.gps_provider_id"
                :options="providerOptions"
                clearable
                @update:model-value="patch({ gps_provider_id: ($event as number | '') ?? '' })"
              />
            </div>

            <div>
              <p class="mb-1 text-sm font-semibold">{{ t('gpsMap.season') }}</p>
              <AppSelect
                :model-value="form.season_id"
                :options="seasonOptions"
                data-test="gps-devices-season"
                @update:model-value="patch({ season_id: ($event as number) ?? null, transport_season_vehicle_id: null })"
              />
            </div>

            <div>
              <p class="mb-1 text-sm font-semibold">{{ t('gpsDevices.vehicle') }}</p>
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
              <span class="mt-1 block text-xs text-brand-text-muted">{{ t('gpsDevices.vehicleHint') }}</span>
            </div>
          </div>

          <footer class="flex shrink-0 justify-end gap-2 border-t border-brand-border px-4 py-4 sm:px-6">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-4 py-2.5 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('gpsProviders.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting || !canSubmit"
              data-test="gps-devices-link"
              @click="emit('submit')"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t('gpsProviders.linkDevice') }}
            </button>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.gps-drawer-enter-active,
.gps-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.gps-drawer-enter-active .gps-drawer-panel,
.gps-drawer-leave-active .gps-drawer-panel {
  transition: transform 0.22s ease;
}
.gps-drawer-enter-from,
.gps-drawer-leave-to {
  opacity: 0;
}
.gps-drawer-enter-from .gps-drawer-panel,
.gps-drawer-leave-to .gps-drawer-panel {
  transform: translateX(1.25rem);
}
@media (prefers-reduced-motion: reduce) {
  .gps-drawer-enter-active,
  .gps-drawer-leave-active,
  .gps-drawer-enter-active .gps-drawer-panel,
  .gps-drawer-leave-active .gps-drawer-panel {
    transition: none;
  }
}
</style>
