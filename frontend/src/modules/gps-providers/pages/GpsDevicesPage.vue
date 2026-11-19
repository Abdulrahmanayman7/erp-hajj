<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Link2, RefreshCw } from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { ApiError } from '@/shared/api/http'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import GpsDeviceLinkDrawer, {
  type GpsDeviceLinkFormState,
} from '../components/GpsDeviceLinkDrawer.vue'
import {
  createGpsDeviceBinding,
  deleteGpsDeviceBinding,
  listGpsDeviceBindings,
  listGpsProviders,
} from '../api/gpsProvidersApi'
import { bindingFreshnessTone, gpsToneClass } from '../utils/gpsStatus'

const { t } = useI18n()
const { can } = usePermissions()
const { confirm } = useConfirm()
const queryClient = useQueryClient()

const canView = computed(
  () => can('gps.devices.manage') || can('gps.providers.view') || can('gps.providers.manage'),
)
const canManage = computed(() => can('gps.devices.manage'))

const search = ref('')
const activeFilter = ref<'all' | '1' | '0'>('all')
const providerFilter = ref<number | ''>('')
const flash = ref<string | null>(null)
const linkOpen = ref(false)
const formError = ref<string | null>(null)

const emptyLinkForm = (): GpsDeviceLinkFormState => ({
  device_id: '',
  external_device_id: '',
  season_id: null,
  transport_season_vehicle_id: null,
  gps_provider_id: '',
})

const linkForm = ref<GpsDeviceLinkFormState>(emptyLinkForm())

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

const linkProviderOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('gpsDevices.noProvider') },
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

const summary = computed(() => {
  const rows = bindingsQuery.data.value ?? []
  return {
    total: rows.length,
    linked: rows.filter((row) => row.transport_season_vehicle_id != null).length,
    inactive: rows.filter((row) => !row.is_active).length,
  }
})

const bindMutation = useMutation({
  mutationFn: () => {
    if (linkForm.value.transport_season_vehicle_id == null) {
      throw new Error('vehicle required')
    }
    return createGpsDeviceBinding({
      device_id: linkForm.value.device_id.trim(),
      external_device_id:
        linkForm.value.external_device_id.trim() || linkForm.value.device_id.trim(),
      transport_season_vehicle_id: linkForm.value.transport_season_vehicle_id,
      gps_provider_id:
        linkForm.value.gps_provider_id === '' ? null : Number(linkForm.value.gps_provider_id),
      is_active: true,
    })
  },
  onSuccess: async () => {
    linkForm.value = emptyLinkForm()
    linkOpen.value = false
    formError.value = null
    flash.value = t('gpsProviders.deviceLinked')
    await queryClient.invalidateQueries({ queryKey: ['gps-device-bindings'] })
  },
  onError: (error) => {
    if (error instanceof ApiError) {
      if (error.status === 422) {
        formError.value = error.message || t('gpsDevices.linkValidationError')
        return
      }
      if (error.status === 403) {
        formError.value = t('gpsDevices.linkPermissionDenied')
        return
      }
      formError.value = error.message
      return
    }
    formError.value = t('gpsDevices.linkFailed')
  },
})

const unbindMutation = useMutation({
  mutationFn: (id: number) => deleteGpsDeviceBinding(id),
  onSuccess: async () => {
    flash.value = t('gpsProviders.deviceUnlinked')
    await queryClient.invalidateQueries({ queryKey: ['gps-device-bindings'] })
  },
})

async function onUnlink(id: number, label: string) {
  const ok = await confirm({
    title: t('gpsDevices.unlinkTitle'),
    message: t('gpsDevices.unlinkMessage', { device: label }),
    confirmLabel: t('gpsProviders.unlink'),
    cancelLabel: t('gpsProviders.cancel'),
    variant: 'danger',
  })
  if (ok) unbindMutation.mutate(id)
}

function freshnessLabel(row: NonNullable<typeof bindingsQuery.data.value>[number]): string {
  if (!row.last_known) return t('gpsDevices.noFix')
  if (row.last_known.is_simulated) return t('executiveDashboard.gpsMarkerStatus.simulated')
  return t(`executiveDashboard.gpsFreshness.${row.last_known.freshness_class}`)
}

function openLink() {
  linkForm.value = emptyLinkForm()
  formError.value = null
  linkOpen.value = true
}
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-4 p-4 md:p-5" dir="rtl">
    <AppPageHeader :title="t('gpsDevices.title')" :subtitle="t('gpsDevices.subtitle')">
      <template #actions>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-white px-3 text-sm font-semibold"
          :disabled="bindingsQuery.isFetching.value"
          @click="bindingsQuery.refetch()"
        >
          <RefreshCw class="h-4 w-4" />
          {{ t('gpsProviders.refresh') }}
        </button>
        <button
          v-if="canManage"
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary px-3 text-sm font-semibold text-white"
          data-test="gps-devices-open-link"
          @click="openLink"
        >
          <Link2 class="h-4 w-4" />
          {{ t('gpsDevices.linkTitle') }}
        </button>
      </template>
    </AppPageHeader>

    <p v-if="flash" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ flash }}</p>

    <div class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-xl border border-brand-border bg-white px-4 py-3">
        <p class="text-xs font-semibold text-brand-text-muted">{{ t('gpsDevices.kpiTotal') }}</p>
        <p class="mt-1 text-2xl font-bold">{{ summary.total }}</p>
      </div>
      <div class="rounded-xl border border-brand-border bg-white px-4 py-3">
        <p class="text-xs font-semibold text-brand-text-muted">{{ t('gpsDevices.kpiLinked') }}</p>
        <p class="mt-1 text-2xl font-bold">{{ summary.linked }}</p>
      </div>
      <div class="rounded-xl border border-brand-border bg-white px-4 py-3">
        <p class="text-xs font-semibold text-brand-text-muted">{{ t('gpsDevices.kpiInactive') }}</p>
        <p class="mt-1 text-2xl font-bold">{{ summary.inactive }}</p>
      </div>
    </div>

    <div class="grid gap-3 rounded-xl border border-brand-border bg-white p-3 md:grid-cols-3">
      <label class="block text-sm">
        <span class="mb-1 block text-brand-text-muted">{{ t('gpsDevices.search') }}</span>
        <input v-model="search" class="w-full rounded-xl border px-3 py-2" data-test="gps-devices-search" />
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

    <div v-if="bindingsQuery.isLoading.value" class="space-y-2">
      <div v-for="n in 4" :key="n" class="h-14 animate-pulse rounded-xl bg-[var(--surface-subtle)]" />
    </div>
    <div v-else-if="bindingsQuery.isError.value" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      <p>{{ t('gpsDevices.loadError') }}</p>
      <button type="button" class="mt-2 font-semibold underline" @click="bindingsQuery.refetch()">
        {{ t('gpsProviders.retry') }}
      </button>
    </div>
    <template v-else>
      <div class="hidden overflow-x-auto rounded-xl border border-brand-border bg-white md:block">
        <table class="app-entity-table min-w-full text-sm" data-test="gps-devices-table">
          <thead class="bg-[var(--surface-subtle)] text-start text-xs text-brand-text-muted">
            <tr>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsProviders.deviceId') }}</th>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsDevices.vehicle') }}</th>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsDevices.providerFilter') }}</th>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsDevices.lastFix') }}</th>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsDevices.lastUpdate') }}</th>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsDevices.health') }}</th>
              <th class="px-3 py-2.5 font-semibold">{{ t('gpsDevices.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in bindingsQuery.data.value ?? []"
              :key="row.id"
              class="border-t border-brand-border"
            >
              <td class="px-3 py-2.5">
                <div class="font-semibold">{{ row.device_id }}</div>
                <div class="text-xs text-brand-text-muted">{{ row.external_device_id }}</div>
              </td>
              <td class="px-3 py-2.5">
                <div>{{ row.plate_number || row.vehicle_number || `#${row.transport_season_vehicle_id}` }}</div>
                <div class="text-xs text-brand-text-muted">{{ row.vehicle_class || '—' }}</div>
              </td>
              <td class="px-3 py-2.5">
                <div>{{ row.provider_name || '—' }}</div>
                <div class="text-xs text-brand-text-muted">{{ row.provider_code || '—' }}</div>
              </td>
              <td class="px-3 py-2.5">
                <span
                  class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                  :class="gpsToneClass[bindingFreshnessTone(row)]"
                >
                  {{ freshnessLabel(row) }}
                </span>
              </td>
              <td class="px-3 py-2.5 text-xs text-brand-text-muted">
                {{ row.last_known?.recorded_at || '—' }}
              </td>
              <td class="px-3 py-2.5">
                <span>{{ row.provider_health || '—' }}</span>
                <span v-if="!row.is_active" class="ms-2 text-xs text-amber-700">{{ t('gpsDevices.inactive') }}</span>
              </td>
              <td class="px-3 py-2.5">
                <button
                  v-if="canManage"
                  type="button"
                  class="rounded-lg border px-2 py-1 text-xs font-semibold"
                  @click="onUnlink(row.id, row.device_id)"
                >
                  {{ t('gpsProviders.unlink') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p
          v-if="!(bindingsQuery.data.value?.length)"
          class="px-3 py-8 text-center text-sm text-brand-text-muted"
          data-test="gps-devices-empty"
        >
          {{ t('gpsDevices.empty') }}
        </p>
      </div>

      <div class="space-y-2 md:hidden" data-test="gps-devices-cards">
        <article
          v-for="row in bindingsQuery.data.value ?? []"
          :key="row.id"
          class="rounded-xl border border-brand-border bg-white p-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-semibold">{{ row.device_id }}</p>
              <p class="text-xs text-brand-text-muted">
                {{ row.plate_number || row.vehicle_number || `#${row.transport_season_vehicle_id}` }}
              </p>
            </div>
            <span
              class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
              :class="gpsToneClass[bindingFreshnessTone(row)]"
            >
              {{ freshnessLabel(row) }}
            </span>
          </div>
          <p class="mt-2 text-xs text-brand-text-muted">
            {{ row.provider_name || '—' }} · {{ row.last_known?.recorded_at || t('gpsDevices.noFix') }}
          </p>
          <button
            v-if="canManage"
            type="button"
            class="mt-3 rounded-lg border px-3 py-1.5 text-xs font-semibold"
            @click="onUnlink(row.id, row.device_id)"
          >
            {{ t('gpsProviders.unlink') }}
          </button>
        </article>
        <p
          v-if="!(bindingsQuery.data.value?.length)"
          class="rounded-xl border border-dashed px-3 py-8 text-center text-sm text-brand-text-muted"
        >
          {{ t('gpsDevices.empty') }}
        </p>
      </div>
    </template>

    <GpsDeviceLinkDrawer
      :open="linkOpen"
      :form="linkForm"
      :provider-options="linkProviderOptions"
      :submitting="bindMutation.isPending.value"
      :form-error="formError"
      @update:form="linkForm = $event"
      @close="linkOpen = false"
      @submit="bindMutation.mutate()"
    />
  </div>
</template>
