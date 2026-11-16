<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import {
  createGpsDeviceBinding,
  createGpsProvider,
  deleteGpsDeviceBinding,
  deleteGpsProvider,
  listGpsDeviceBindings,
  listGpsProviders,
  syncGpsProvider,
  testGpsProvider,
  updateGpsProvider,
} from '../api/gpsProvidersApi'
import type { GpsConnectionMethod, GpsProvider } from '../types/gpsProviders'

const { t } = useI18n()
const { can } = usePermissions()
const queryClient = useQueryClient()

const canView = computed(() => can('gps.providers.view') || can('gps.providers.manage'))
const canManage = computed(() => can('gps.providers.manage'))
const canDevices = computed(() => can('gps.devices.manage'))

const selectedId = ref<number | null>(null)
const formOpen = ref(false)
const formName = ref('')
const formCode = ref('')
const formMethod = ref<GpsConnectionMethod>('webhook')
const formEnabled = ref(false)
const formBaseUrl = ref('')
const formEndpointUrl = ref('')
const formSecret = ref('')
const formToken = ref('')
const formEmail = ref('')
const formPassword = ref('')
const formPoll = ref('300')
const editingId = ref<number | null>(null)
const flash = ref<string | null>(null)
const deviceId = ref('')
const externalDeviceId = ref('')
const vehicleId = ref('')

const methodOptions = computed<AppSelectOption[]>(() => [
  { value: 'webhook', label: 'Webhook آمن' },
  { value: 'rest_pull', label: 'REST API (سحب)' },
  { value: 'traccar', label: 'Traccar' },
  { value: 'mobile_driver', label: 'جوال السائق (معطّل حتى التفويض)' },
  { value: 'simulator', label: 'محاكي DEMO' },
])

const providersQuery = useQuery({
  queryKey: ['gps-providers'],
  queryFn: listGpsProviders,
  enabled: canView,
})

const bindingsQuery = useQuery({
  queryKey: computed(() => ['gps-device-bindings', selectedId.value]),
  queryFn: () => listGpsDeviceBindings({ providerId: selectedId.value ?? undefined }),
  enabled: computed(() => canView.value && selectedId.value != null),
})

const selected = computed(
  () => providersQuery.data.value?.find((row) => row.id === selectedId.value) ?? null,
)

const simFailure = ref(false)
const simOfflineDevices = ref('')
const simRadius = ref('3')
const simPoll = ref('60')

watch(selected, (row) => {
  if (!row || row.connection_method !== 'simulator') return
  simFailure.value = Boolean(row.settings.simulate_failure)
  const offline = row.settings.offline_device_ids
  simOfflineDevices.value = Array.isArray(offline) ? offline.map(String).join(', ') : ''
  simRadius.value = row.settings.radius_km != null ? String(row.settings.radius_km) : '3'
  simPoll.value = row.poll_interval_seconds != null ? String(row.poll_interval_seconds) : '60'
})

const simulatorMutation = useMutation({
  mutationFn: async () => {
    if (selectedId.value == null) throw new Error('no provider')
    const offline = simOfflineDevices.value
      .split(',')
      .map((part) => part.trim())
      .filter(Boolean)
    return updateGpsProvider(selectedId.value, {
      name: selected.value?.name ?? 'simulator',
      connection_method: 'simulator',
      is_enabled: selected.value?.is_enabled ?? true,
      poll_interval_seconds: Number(simPoll.value) || 60,
      settings: {
        base_latitude: Number(selected.value?.settings.base_latitude ?? 21.4225),
        base_longitude: Number(selected.value?.settings.base_longitude ?? 39.8262),
        radius_km: Number(simRadius.value) || 3,
        simulate_failure: simFailure.value,
        offline_device_ids: offline,
      },
    })
  },
  onSuccess: async () => {
    flash.value = t('gpsProviders.simulatorSaved')
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

watch(
  () => providersQuery.data.value,
  (rows) => {
    if (rows && rows.length > 0 && selectedId.value == null) {
      selectedId.value = rows[0]!.id
    }
  },
)

function resetForm() {
  editingId.value = null
  formName.value = ''
  formCode.value = ''
  formMethod.value = 'webhook'
  formEnabled.value = false
  formBaseUrl.value = ''
  formEndpointUrl.value = ''
  formSecret.value = ''
  formToken.value = ''
  formEmail.value = ''
  formPassword.value = ''
  formPoll.value = '300'
}

function openCreate() {
  resetForm()
  formOpen.value = true
}

function openEdit(row: GpsProvider) {
  editingId.value = row.id
  formName.value = row.name
  formCode.value = row.code
  formMethod.value = row.connection_method
  formEnabled.value = row.is_enabled
  formPoll.value = row.poll_interval_seconds != null ? String(row.poll_interval_seconds) : ''
  formBaseUrl.value = String(row.settings.base_url ?? '')
  formEndpointUrl.value = String(row.settings.endpoint_url ?? '')
  formSecret.value = ''
  formToken.value = ''
  formEmail.value = ''
  formPassword.value = ''
  formOpen.value = true
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const settings: Record<string, unknown> = {}
    if (formBaseUrl.value.trim()) settings.base_url = formBaseUrl.value.trim()
    if (formEndpointUrl.value.trim()) settings.endpoint_url = formEndpointUrl.value.trim()
    if (formMethod.value === 'mobile_driver') {
      settings.require_explicit_consent = true
      settings.tracking_enabled = false
    }
    if (formMethod.value === 'simulator') {
      settings.base_latitude = 21.4225
      settings.base_longitude = 39.8262
      settings.radius_km = 3
    }

    const credentials: Record<string, string> = {}
    if (formSecret.value.trim()) credentials.webhook_secret = formSecret.value.trim()
    if (formToken.value.trim()) credentials.api_token = formToken.value.trim()
    if (formEmail.value.trim()) credentials.email = formEmail.value.trim()
    if (formPassword.value.trim()) credentials.password = formPassword.value.trim()

    const poll = formPoll.value.trim() === '' ? null : Number(formPoll.value)

    if (editingId.value != null) {
      return updateGpsProvider(editingId.value, {
        name: formName.value.trim(),
        connection_method: formMethod.value,
        is_enabled: formEnabled.value,
        poll_interval_seconds: poll,
        settings,
        credentials: Object.keys(credentials).length ? credentials : undefined,
      })
    }

    return createGpsProvider({
      name: formName.value.trim(),
      code: formCode.value.trim(),
      connection_method: formMethod.value,
      is_enabled: formEnabled.value,
      poll_interval_seconds: poll,
      settings,
      credentials: Object.keys(credentials).length ? credentials : undefined,
    })
  },
  onSuccess: async (row) => {
    flash.value = t('gpsProviders.saved')
    formOpen.value = false
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
    selectedId.value = row.id
  },
})

const testMutation = useMutation({
  mutationFn: (id: number) => {
    flash.value = null
    return testGpsProvider(id)
  },
  onSuccess: async (result) => {
    flash.value = result.message
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

const syncMutation = useMutation({
  mutationFn: (id: number) => {
    flash.value = null
    return syncGpsProvider(id)
  },
  onSuccess: async (result) => {
    flash.value = result.message
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => deleteGpsProvider(id),
  onSuccess: async () => {
    flash.value = t('gpsProviders.deleted')
    selectedId.value = null
    await queryClient.invalidateQueries({ queryKey: ['gps-providers'] })
  },
})

const bindMutation = useMutation({
  mutationFn: () =>
    createGpsDeviceBinding({
      device_id: deviceId.value.trim(),
      external_device_id: externalDeviceId.value.trim() || deviceId.value.trim(),
      transport_season_vehicle_id: Number(vehicleId.value),
      gps_provider_id: selectedId.value,
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
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-6 p-4 md:p-6" dir="rtl">
    <AppPageHeader :title="t('gpsProviders.title')" :subtitle="t('gpsProviders.subtitle')" />

    <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
      {{ t('gpsProviders.g4Notice') }}
    </p>

    <p v-if="flash" class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-900" data-test="gps-flash">
      {{ flash }}
    </p>

    <div class="flex flex-wrap items-center gap-2">
      <button
        v-if="canManage"
        type="button"
        class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
        data-test="gps-add-provider"
        @click="openCreate"
      >
        {{ t('gpsProviders.add') }}
      </button>
      <button
        type="button"
        class="rounded-md border border-[var(--border-subtle)] px-3 py-2 text-sm"
        :disabled="providersQuery.isFetching.value"
        @click="providersQuery.refetch()"
      >
        {{ t('gpsProviders.refresh') }}
      </button>
    </div>

    <div v-if="providersQuery.isLoading.value" class="text-sm text-brand-text-muted">
      {{ t('gpsProviders.loading') }}
    </div>
    <div v-else-if="providersQuery.isError.value" class="text-sm text-red-700">
      {{ t('gpsProviders.loadError') }}
    </div>
    <div v-else class="grid gap-4 lg:grid-cols-2">
      <section class="space-y-3 rounded-lg border border-[var(--border-subtle)] bg-white p-4">
        <h2 class="text-sm font-bold">{{ t('gpsProviders.listTitle') }}</h2>
        <p v-if="!(providersQuery.data.value?.length)" class="text-sm text-brand-text-muted">
          {{ t('gpsProviders.empty') }}
        </p>
        <ul class="space-y-2" data-test="gps-provider-list">
          <li
            v-for="row in providersQuery.data.value"
            :key="row.id"
            class="cursor-pointer rounded-md border px-3 py-2"
            :class="selectedId === row.id ? 'border-brand-primary bg-[var(--surface-subtle)]' : 'border-[var(--border-subtle)]'"
            @click="selectedId = row.id"
          >
            <div class="flex items-center justify-between gap-2">
              <div>
                <p class="font-semibold text-brand-text">{{ row.name }}</p>
                <p class="text-xs text-brand-text-muted">{{ row.code }} · {{ row.connection_method }}</p>
              </div>
              <span class="text-xs" :data-test="`gps-health-${row.id}`">
                {{ row.last_health_status ?? 'unknown' }}
              </span>
            </div>
          </li>
        </ul>
      </section>

      <section v-if="selected" class="space-y-3 rounded-lg border border-[var(--border-subtle)] bg-white p-4">
        <h2 class="text-sm font-bold">{{ selected.name }}</h2>
        <dl class="grid grid-cols-2 gap-2 text-sm">
          <div>
            <dt class="text-brand-text-muted">{{ t('gpsProviders.method') }}</dt>
            <dd>{{ selected.connection_method }}</dd>
          </div>
          <div>
            <dt class="text-brand-text-muted">{{ t('gpsProviders.enabled') }}</dt>
            <dd>{{ selected.is_enabled ? t('gpsProviders.yes') : t('gpsProviders.no') }}</dd>
          </div>
          <div>
            <dt class="text-brand-text-muted">{{ t('gpsProviders.credentials') }}</dt>
            <dd>{{ selected.has_credentials ? selected.credentials_masked : t('gpsProviders.none') }}</dd>
          </div>
          <div>
            <dt class="text-brand-text-muted">{{ t('gpsProviders.lastSync') }}</dt>
            <dd>{{ selected.last_sync_at ?? '—' }}</dd>
          </div>
        </dl>
        <p v-if="selected.last_error_sanitized" class="text-sm text-red-700">{{ selected.last_error_sanitized }}</p>
        <div class="flex flex-wrap gap-2">
          <button v-if="canManage" type="button" class="rounded-md border px-3 py-1.5 text-sm" @click="openEdit(selected)">
            {{ t('gpsProviders.edit') }}
          </button>
          <button
            v-if="canManage"
            type="button"
            class="rounded-md border px-3 py-1.5 text-sm"
            :disabled="testMutation.isPending.value"
            @click="testMutation.mutate(selected.id)"
          >
            {{ t('gpsProviders.testConnection') }}
          </button>
          <button
            v-if="canManage"
            type="button"
            class="rounded-md border px-3 py-1.5 text-sm"
            :disabled="syncMutation.isPending.value"
            @click="syncMutation.mutate(selected.id)"
          >
            {{ t('gpsProviders.syncNow') }}
          </button>
          <button
            v-if="canManage"
            type="button"
            class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700"
            @click="deleteMutation.mutate(selected.id)"
          >
            {{ t('gpsProviders.delete') }}
          </button>
        </div>

        <div
          v-if="selected.connection_method === 'simulator' && canManage"
          class="space-y-2 border-t border-[var(--border-subtle)] pt-3"
          data-test="gps-simulator-controls"
        >
          <h3 class="text-sm font-bold">{{ t('gpsProviders.simulatorTitle') }}</h3>
          <p class="text-xs text-amber-800">{{ t('gpsProviders.simulatorHint') }}</p>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="simFailure" type="checkbox" data-test="gps-sim-failure" />
            {{ t('gpsProviders.simulateFailure') }}
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.offlineDevices') }}</span>
            <input v-model="simOfflineDevices" class="w-full rounded-md border px-2 py-1.5" data-test="gps-sim-offline" />
          </label>
          <div class="grid gap-2 md:grid-cols-2">
            <label class="block text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.radiusKm') }}</span>
              <input v-model="simRadius" type="number" min="0.1" step="0.1" class="w-full rounded-md border px-2 py-1.5" />
            </label>
            <label class="block text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.pollSeconds') }}</span>
              <input v-model="simPoll" type="number" min="30" class="w-full rounded-md border px-2 py-1.5" />
            </label>
          </div>
          <button
            type="button"
            class="rounded-md border px-3 py-1.5 text-sm"
            :disabled="simulatorMutation.isPending.value"
            @click="simulatorMutation.mutate()"
          >
            {{ t('gpsProviders.saveSimulator') }}
          </button>
        </div>

        <div class="border-t border-[var(--border-subtle)] pt-3">
          <h3 class="mb-2 text-sm font-bold">{{ t('gpsProviders.devicesTitle') }}</h3>
          <ul class="mb-3 space-y-1 text-sm" data-test="gps-device-list">
            <li
              v-for="binding in bindingsQuery.data.value ?? []"
              :key="binding.id"
              class="flex items-center justify-between gap-2"
            >
              <span>{{ binding.device_id }} → مركبة #{{ binding.transport_season_vehicle_id }}</span>
              <button
                v-if="canDevices"
                type="button"
                class="rounded-md border px-2 py-1 text-xs"
                @click="unbindMutation.mutate(binding.id)"
              >
                {{ t('gpsProviders.unlink') }}
              </button>
            </li>
          </ul>
          <div v-if="canDevices" class="grid gap-2 md:grid-cols-3">
            <label class="text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.deviceId') }}</span>
              <input v-model="deviceId" class="w-full rounded-md border px-2 py-1.5" data-test="gps-device-id" />
            </label>
            <label class="text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.externalDeviceId') }}</span>
              <input v-model="externalDeviceId" class="w-full rounded-md border px-2 py-1.5" />
            </label>
            <label class="text-sm">
              <span class="mb-1 block text-brand-text-muted">{{ t('gpsProviders.vehicleId') }}</span>
              <input v-model="vehicleId" class="w-full rounded-md border px-2 py-1.5" data-test="gps-vehicle-id" />
            </label>
            <button
              type="button"
              class="rounded-md bg-brand-primary px-3 py-2 text-sm font-semibold text-white md:col-span-3"
              :disabled="bindMutation.isPending.value"
              @click="bindMutation.mutate()"
            >
              {{ t('gpsProviders.linkDevice') }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <div
      v-if="formOpen"
      class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 md:items-center"
      data-test="gps-provider-form"
    >
      <form class="w-full max-w-lg space-y-3 rounded-lg bg-white p-4 shadow-lg" @submit.prevent="saveMutation.mutate()">
        <h2 class="text-lg font-bold">{{ editingId ? t('gpsProviders.edit') : t('gpsProviders.add') }}</h2>
        <label class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.name') }}</span>
          <input v-model="formName" required class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label v-if="!editingId" class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.code') }}</span>
          <input v-model="formCode" required class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <AppSelect v-model="formMethod" :label="t('gpsProviders.method')" :options="methodOptions" />
        <label class="flex items-center gap-2 text-sm">
          <input v-model="formEnabled" type="checkbox" />
          {{ t('gpsProviders.enabled') }}
        </label>
        <label v-if="formMethod === 'traccar'" class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.baseUrl') }}</span>
          <input v-model="formBaseUrl" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label v-if="formMethod === 'rest_pull'" class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.endpointUrl') }}</span>
          <input v-model="formEndpointUrl" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.webhookSecret') }}</span>
          <input v-model="formSecret" type="password" autocomplete="new-password" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.apiToken') }}</span>
          <input v-model="formToken" type="password" autocomplete="new-password" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label v-if="formMethod === 'traccar'" class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.email') }}</span>
          <input v-model="formEmail" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <label v-if="formMethod === 'traccar'" class="block text-sm">
          <span class="mb-1 block">{{ t('gpsProviders.password') }}</span>
          <input v-model="formPassword" type="password" autocomplete="new-password" class="w-full rounded-md border px-2 py-1.5" />
        </label>
        <p class="text-xs text-brand-text-muted">{{ t('gpsProviders.secretHint') }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border px-3 py-1.5 text-sm" @click="formOpen = false">
            {{ t('gpsProviders.cancel') }}
          </button>
          <button
            type="submit"
            class="rounded-md bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white"
            :disabled="saveMutation.isPending.value"
          >
            {{ t('gpsProviders.save') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
